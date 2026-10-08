<?php

namespace Wexample\SymfonyMedia\Tests\Integration;

use InvalidArgumentException;
use Wexample\SymfonyMedia\Repository\MediaRepository;
use Wexample\SymfonyMedia\Service\MediaStorage;
use Wexample\SymfonyMedia\Service\MediaThumbnailService;

class StorageTest extends AbstractMediaTestCase
{
    public function testAnImageIsTakenInDescribedAndRemoved(): void
    {
        $storage = $this->service(MediaStorage::class);
        $upload = $this->png();

        $media = $storage->import($upload, 'Cover Photo.PNG');
        $path = $storage->getPath($media);

        $this->assertFileDoesNotExist($upload);
        $this->assertFileExists($path);
        $this->assertSame($this->storageDir.'/'.substr((string) $media->getId(), 0, 2).'/'.$media->getId().'.png', $path);
        $this->assertSame('Cover Photo.PNG', $media->getName());
        $this->assertSame('image/png', $media->getMimeType());
        $this->assertSame([400, 200], [$media->getWidth(), $media->getHeight()]);
        $this->assertSame(filesize($path), $media->getSize());

        $thumbnail = $this->service(MediaThumbnailService::class)->getPath($media, $path, 160);
        $this->assertSame([160, 80], array_slice(getimagesize($thumbnail), 0, 2));
        $this->assertSame('image/webp', getimagesize($thumbnail)['mime']);

        $storage->delete($media);
        $this->assertFileDoesNotExist($path);
        $this->assertFileDoesNotExist($thumbnail);
        $this->assertSame(0, $this->service(MediaRepository::class)->count([]));
    }

    public function testAFileWithoutPicturesHasNoThumbnail(): void
    {
        $storage = $this->service(MediaStorage::class);
        $media = $storage->import($this->text(), 'notes.txt');

        $this->assertSame('text/plain', $media->getMimeType());
        $this->assertNull($media->getWidth());
        $this->assertNull($this->service(MediaThumbnailService::class)->getPath($media, $storage->getPath($media), 160));
    }

    public function testOnlyTheConfiguredSizesAreDrawn(): void
    {
        $storage = $this->service(MediaStorage::class);
        $media = $storage->import($this->png(), 'a.png');

        $this->expectException(InvalidArgumentException::class);
        $this->service(MediaThumbnailService::class)->getPath($media, $storage->getPath($media), 333);
    }

    public function testASmallImageIsNotEnlarged(): void
    {
        $storage = $this->service(MediaStorage::class);
        $media = $storage->import($this->png(100, 50), 'small.png');

        $thumbnail = $this->service(MediaThumbnailService::class)->getPath($media, $storage->getPath($media), 480);
        $this->assertSame([100, 50], array_slice(getimagesize($thumbnail), 0, 2));
    }
}
