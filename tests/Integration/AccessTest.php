<?php

namespace Wexample\SymfonyMedia\Tests\Integration;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Wexample\SymfonyMedia\Controller\MediaFileController;
use Wexample\SymfonyMedia\Service\MediaStorage;

class AccessTest extends AbstractMediaTestCase
{
    public function testNobodySignedInReadsNothing(): void
    {
        $media = $this->service(MediaStorage::class)->import($this->png(), 'a.png');

        $this->expectException(AccessDeniedException::class);
        $this->service(MediaFileController::class)->file((string) $media->getId(), new Request());
    }

    public function testAnImageIsShownAndAnythingElseDownloaded(): void
    {
        $storage = $this->service(MediaStorage::class);
        $image = $storage->import($this->png(), 'a.png');
        $page = $storage->import($this->text('<html><script>alert(1)</script></html>'), 'page.html');
        $controller = $this->service(MediaFileController::class);
        $this->signIn('ROLE_USER');

        $shown = $controller->file((string) $image->getId(), new Request());
        $this->assertStringStartsWith(ResponseHeaderBag::DISPOSITION_INLINE, $shown->headers->get('Content-Disposition'));
        $this->assertSame('sandbox', $shown->headers->get('Content-Security-Policy'));

        $downloaded = $controller->file((string) $page->getId(), new Request());
        $this->assertStringStartsWith(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloaded->headers->get('Content-Disposition'));

        $asked = $controller->file((string) $image->getId(), new Request(['download' => '1']));
        $this->assertStringStartsWith(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $asked->headers->get('Content-Disposition'));

        $thumbnail = $controller->thumbnail((string) $image->getId(), 160);
        $this->assertSame('image/webp', $thumbnail->headers->get('Content-Type'));
    }

    public function testAnUnknownOrMalformedIdIsNotFound(): void
    {
        $this->signIn('ROLE_USER');

        $this->expectException(NotFoundHttpException::class);
        $this->service(MediaFileController::class)->file('not-an-id', new Request());
    }

    public function testArrangingTheLibraryNeedsTheManageRole(): void
    {
        $checker = self::getContainer()->get('security.authorization_checker');

        $this->signIn('ROLE_USER');
        $this->assertTrue($checker->isGranted('MEDIA_VIEW'));
        $this->assertFalse($checker->isGranted('MEDIA_MANAGE'));

        $this->signIn('ROLE_ADMIN');
        $this->assertTrue($checker->isGranted('MEDIA_MANAGE'));
    }
}
