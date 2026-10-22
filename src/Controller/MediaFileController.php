<?php

namespace Wexample\SymfonyMedia\Controller;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyMedia\Entity\Media;
use Wexample\SymfonyMedia\Repository\MediaAccessGrantRepository;
use Wexample\SymfonyMedia\Repository\MediaRepository;
use Wexample\SymfonyMedia\Security\MediaVoter;
use Wexample\SymfonyMedia\Service\MediaAccessService;
use Wexample\SymfonyMedia\Service\MediaStorage;
use Wexample\SymfonyMedia\Service\MediaThumbnailService;

/**
 * The files of the library, each read through the access check.
 *
 * A file the browser can show safely — an image it decodes itself, a PDF, a
 * sound, a video — is sent inline; anything else, an SVG or an HTML file
 * among them, which could run a script under the application's address, is
 * downloaded. Every answer is sandboxed all the same.
 *
 * A grant's link (MediaAccessService) downloads its media without the
 * library's roles, as long as its signature and the grant hold.
 */
#[Route(path: '/_media/', name: 'media_')]
final class MediaFileController
{
    public const string ROUTE_FILE = 'file';

    public const string ROUTE_FILE_NAME = 'media_file';

    public const string ROUTE_THUMBNAIL = 'thumbnail';

    public const string ROUTE_THUMBNAIL_NAME = 'media_thumbnail';

    public const string ROUTE_GRANT = 'grant';

    public const string ROUTE_GRANT_NAME = 'media_grant';

    private const array INLINE_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif',
        'application/pdf',
        'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/webm',
        'video/mp4', 'video/webm', 'video/ogg',
        'text/plain',
    ];

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly MediaStorage $storage,
        private readonly MediaThumbnailService $thumbnails,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly MediaAccessGrantRepository $grantRepository,
        private readonly MediaAccessService $accessService,
        private readonly UriSigner $uriSigner,
    ) {
    }

    #[Route(path: '{id}', name: self::ROUTE_FILE, methods: [Request::METHOD_GET])]
    public function file(
        string $id,
        Request $request
    ): BinaryFileResponse {
        $media = $this->find($id);
        $inline = ! $request->query->getBoolean('download') && in_array($media->getMimeType(), self::INLINE_TYPES, true);

        return $this->respond(
            $this->storage->getPath($media),
            (string) $media->getMimeType(),
            $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $media->getName()
        );
    }

    #[Route(path: '{id}/thumbnail/{width}', name: self::ROUTE_THUMBNAIL, requirements: ['width' => '\d+'], methods: [Request::METHOD_GET])]
    public function thumbnail(
        string $id,
        int $width
    ): BinaryFileResponse {
        $media = $this->find($id);

        try {
            $path = $this->thumbnails->getPath($media, $this->storage->getPath($media), $width);
        } catch (InvalidArgumentException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        if (null === $path) {
            throw new NotFoundHttpException('This media has no thumbnail.');
        }

        return $this->respond($path, 'image/webp', ResponseHeaderBag::DISPOSITION_INLINE, $media->getName().'.webp');
    }

    /**
     * A link tampered with, or past its date, leads nowhere; a grant used up is gone.
     */
    #[Route(path: 'grant/{id}', name: self::ROUTE_GRANT, methods: [Request::METHOD_GET])]
    public function grant(
        string $id,
        Request $request
    ): BinaryFileResponse {
        $grant = (Uuid::isValid($id) ? $this->grantRepository->find($id) : null) ?? throw new NotFoundHttpException();

        if (! $this->uriSigner->checkRequest($request)) {
            throw new NotFoundHttpException();
        }

        $media = $grant->getMedia();
        $path = $this->storage->getPath($media);

        if (! is_file($path)) {
            throw new NotFoundHttpException('The file of this media is missing.');
        }

        if (! $this->accessService->consume($grant)) {
            throw new GoneHttpException('This link has been used up or has expired.');
        }

        return $this->respond($path, (string) $media->getMimeType(), ResponseHeaderBag::DISPOSITION_ATTACHMENT, $media->getName());
    }

    private function find(string $id): Media
    {
        $media = (Uuid::isValid($id) ? $this->mediaRepository->find($id) : null) ?? throw new NotFoundHttpException();

        // Thrown rather than answered: the firewall turns it into a sign-in
        // for whoever is not signed in yet.
        if (! $this->authorizationChecker->isGranted(MediaVoter::VIEW, $media)) {
            throw new AccessDeniedException();
        }

        if (! is_file($this->storage->getPath($media))) {
            throw new NotFoundHttpException('The file of this media is missing.');
        }

        return $media;
    }

    private function respond(
        string $path,
        string $mimeType,
        string $disposition,
        string $name
    ): BinaryFileResponse {
        $response = new BinaryFileResponse($path, headers: ['Content-Type' => $mimeType], autoEtag: true, autoLastModified: true);
        $response->setContentDisposition($disposition, $name, $this->asciiFallback($name));
        $response->setPrivate();
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', 'sandbox');

        return $response;
    }

    private function asciiFallback(string $name): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]|[\/\\\\%"]/', '_', $name) ?? '';

        return '' !== $ascii ? $ascii : 'file';
    }
}
