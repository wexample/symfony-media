<?php

namespace Wexample\SymfonyMedia\Tests\Integration;

use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Wexample\SymfonyMedia\Controller\MediaFileController;
use Wexample\SymfonyMedia\Service\MediaAccessService;
use Wexample\SymfonyMedia\Service\MediaLibraryService;
use Wexample\SymfonyMedia\Service\MediaStorage;

/**
 * A restricted media, sold or private: out of the library's lists and reach,
 * downloaded through a grant's signed link while the grant holds.
 */
class AccessGrantTest extends AbstractMediaTestCase
{
    public function testARestrictedMediaIsTheManagersOnly(): void
    {
        $media = $this->service(MediaStorage::class)->import($this->text('the book'), 'book.pdf')->setRestricted(true);
        $this->entityManager->flush();

        $this->assertSame(0, $this->service(MediaLibraryService::class)->level(null, 0, 10, withRestricted: false)['total']);

        $this->signIn('ROLE_ADMIN');
        $this->assertSame(200, $this->service(MediaFileController::class)->file((string) $media->getId(), new Request())->getStatusCode());

        $this->signIn('ROLE_USER');
        $this->expectException(AccessDeniedException::class);
        $this->service(MediaFileController::class)->file((string) $media->getId(), new Request());
    }

    public function testAGrantDownloadsAsManyTimesAsItAllows(): void
    {
        $media = $this->service(MediaStorage::class)->import($this->text('the book'), 'book.pdf')->setRestricted(true);
        $access = $this->service(MediaAccessService::class);
        $grant = $access->grant($media, new DateTimeImmutable('+1 day'), maxUses: 2, origin: 'test:1');
        $this->entityManager->flush();

        $url = $access->getUrl($grant);
        $controller = $this->service(MediaFileController::class);

        foreach ([1, 2] as $use) {
            $response = $controller->grant((string) $grant->getId(), Request::create($url));
            $this->assertStringStartsWith(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $response->headers->get('Content-Disposition'));
            $this->assertSame($use, $grant->getUses());
        }

        $this->expectException(GoneHttpException::class);
        $controller->grant((string) $grant->getId(), Request::create($url));
    }

    public function testATamperedOrExpiredLinkLeadsNowhere(): void
    {
        $media = $this->service(MediaStorage::class)->import($this->text('the book'), 'book.pdf');
        $access = $this->service(MediaAccessService::class);
        $expired = $access->grant($media, new DateTimeImmutable('-1 minute'));
        $open = $access->grant($media);
        $this->entityManager->flush();
        $controller = $this->service(MediaFileController::class);

        try {
            $controller->grant((string) $open->getId(), Request::create($access->getUrl($open).'x'));
            $this->fail('A tampered link opened.');
        } catch (NotFoundHttpException) {
        }

        $this->expectException(NotFoundHttpException::class);
        $controller->grant((string) $expired->getId(), Request::create($access->getUrl($expired)));
    }
}
