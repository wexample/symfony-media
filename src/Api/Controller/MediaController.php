<?php

namespace Wexample\SymfonyMedia\Api\Controller;

use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\LengthQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\PageQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\StringQueryOption;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyMedia\Entity\Media;
use Wexample\SymfonyMedia\Entity\MediaFolder;
use Wexample\SymfonyMedia\Exception\MediaLibraryException;
use Wexample\SymfonyMedia\Repository\MediaFolderRepository;
use Wexample\SymfonyMedia\Repository\MediaRepository;
use Wexample\SymfonyMedia\Security\MediaVoter;
use Wexample\SymfonyMedia\Service\MediaLibraryService;

/**
 * The library, browsed and arranged by a screen: a level listed, folders made,
 * anything renamed, moved or deleted. What arranges it takes JSON only, which
 * a page of another site cannot send without asking first.
 *
 * An item is named `{ type: media|folder, id }`.
 */
#[Route(path: 'api/media/', name: 'api_media_')]
class MediaController extends AbstractApiController
{
    final public const string ROUTE_LIST = 'list';

    final public const string ROUTE_FOLDER = 'folder';

    final public const string ROUTE_RENAME = 'rename';

    final public const string ROUTE_MOVE = 'move';

    final public const string ROUTE_DELETE = 'delete';

    final public const string QUERY_OPTION_FOLDER = 'folder';

    final public const int LIST_PAGE_LENGTH = 60;

    public function __construct(
        private readonly MediaLibraryService $library,
        private readonly MediaRepository $mediaRepository,
        private readonly MediaFolderRepository $folderRepository,
    ) {
    }

    #[Route(path: self::ROUTE_LIST, name: self::ROUTE_LIST, methods: AbstractController::ROUTE_OPTIONS_METHOD_ONLY_GET, options: AbstractController::ROUTE_OPTIONS_ONLY_EXPOSE)]
    #[PageQueryOption]
    #[LengthQueryOption(default: self::LIST_PAGE_LENGTH)]
    #[StringQueryOption(key: self::QUERY_OPTION_FOLDER, default: '')]
    public function list(Request $request): ApiResponse
    {
        $this->denyAccessUnlessGranted(MediaVoter::VIEW);

        $folder = $this->findFolder((string) self::getQueryOptionValue($request, self::QUERY_OPTION_FOLDER, ''));
        $pagination = self::getQueryOptionPagination($request);
        $level = $this->library->level($folder, $pagination->getOffset(), $pagination->length ?? self::LIST_PAGE_LENGTH, $this->isGranted(MediaVoter::MANAGE));

        return self::apiResponsePaginated(
            pagination: $pagination->withTotal($level['total']),
            items: $level['items']
        );
    }

    #[Route(path: self::ROUTE_FOLDER, name: self::ROUTE_FOLDER, methods: [Request::METHOD_POST], options: AbstractController::ROUTE_OPTIONS_ONLY_EXPOSE)]
    public function folder(Request $request): ApiResponse
    {
        $body = $this->manage($request);

        return $this->attempt(fn () => self::apiResponseSuccess(data: $this->library->describeFolder(
            $this->library->createFolder((string) ($body['name'] ?? ''), $this->findFolder((string) ($body['parent'] ?? '')))
        )));
    }

    #[Route(path: self::ROUTE_RENAME, name: self::ROUTE_RENAME, methods: [Request::METHOD_POST], options: AbstractController::ROUTE_OPTIONS_ONLY_EXPOSE)]
    public function rename(Request $request): ApiResponse
    {
        $body = $this->manage($request);
        $item = $this->findItem($body);

        return $this->attempt(function () use ($item, $body) {
            $this->library->rename($item, (string) ($body['name'] ?? ''));

            return self::apiResponseSuccess(data: $this->describe($item));
        });
    }

    #[Route(path: self::ROUTE_MOVE, name: self::ROUTE_MOVE, methods: [Request::METHOD_POST], options: AbstractController::ROUTE_OPTIONS_ONLY_EXPOSE)]
    public function move(Request $request): ApiResponse
    {
        $body = $this->manage($request);
        $item = $this->findItem($body);

        return $this->attempt(function () use ($item, $body) {
            $this->library->move($item, $this->findFolder((string) ($body['target'] ?? '')));

            return self::apiResponseSuccess(data: $this->describe($item));
        });
    }

    #[Route(path: self::ROUTE_DELETE, name: self::ROUTE_DELETE, methods: [Request::METHOD_POST], options: AbstractController::ROUTE_OPTIONS_ONLY_EXPOSE)]
    public function delete(Request $request): ApiResponse
    {
        $body = $this->manage($request);
        $item = $this->findItem($body);

        return $this->attempt(function () use ($item) {
            $this->library->delete($item);

            return self::apiResponseSuccess();
        });
    }

    /**
     * @return array<string, mixed> the JSON body
     */
    private function manage(Request $request): array
    {
        $this->denyAccessUnlessGranted(MediaVoter::MANAGE);

        if ('json' !== $request->getContentTypeFormat()) {
            throw new UnsupportedMediaTypeHttpException('JSON only.');
        }

        try {
            return $request->toArray();
        } catch (JsonException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }
    }

    /**
     * What the library refuses is told, as a 400 with its reason.
     */
    private function attempt(callable $action): ApiResponse
    {
        try {
            return $action();
        } catch (MediaLibraryException $exception) {
            return self::apiResponseError($exception, code: Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Media|MediaFolder $item): array
    {
        return $item instanceof Media ? $this->library->describe($item) : $this->library->describeFolder($item);
    }

    /**
     * Null for the root, named by an empty id.
     */
    private function findFolder(string $id): ?MediaFolder
    {
        if ('' === $id) {
            return null;
        }

        return (Uuid::isValid($id) ? $this->folderRepository->find($id) : null)
            ?? throw new NotFoundHttpException('Unknown folder.');
    }

    /**
     * @param array<string, mixed> $body
     */
    private function findItem(array $body): Media|MediaFolder
    {
        $id = (string) ($body['id'] ?? '');
        $repository = match ($body['type'] ?? null) {
            'media' => $this->mediaRepository,
            'folder' => $this->folderRepository,
            default => throw new BadRequestHttpException('An item is a media or a folder.'),
        };

        return (Uuid::isValid($id) ? $repository->find($id) : null)
            ?? throw new NotFoundHttpException('Unknown item.');
    }
}
