<?php

namespace Wexample\SymfonyMedia\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Wexample\SymfonyHelpers\Interface\HeadStructuredDataProviderInterface;
use Wexample\SymfonyMedia\Normalizer\MediaSchemaOrgNormalizer;
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

    public function testAnImageTooLargeToDecodeHasNoThumbnail(): void
    {
        $storage = $this->service(MediaStorage::class);
        $thumbnails = $this->service(MediaThumbnailService::class);
        $media = $storage->import($this->png(), 'bomb.png');

        // What a few kilobytes of png can announce in their header; GD would
        // allocate for it before drawing a single pixel.
        $media->setDimensions(100_000, 100_000);

        $this->assertFalse($thumbnails->supports($media));
        $this->assertNull($thumbnails->getPath($media, $storage->getPath($media), 160));
    }

    public function testADrawnThumbnailLeavesNoPartialFileBehind(): void
    {
        $storage = $this->service(MediaStorage::class);
        $media = $storage->import($this->png(), 'a.png');

        $thumbnail = $this->service(MediaThumbnailService::class)->getPath($media, $storage->getPath($media), 160);

        $this->assertSame([basename($thumbnail)], array_values(array_diff(scandir(dirname($thumbnail)), ['.', '..'])));
    }

    public function testASmallImageIsNotEnlarged(): void
    {
        $storage = $this->service(MediaStorage::class);
        $media = $storage->import($this->png(100, 50), 'small.png');

        $thumbnail = $this->service(MediaThumbnailService::class)->getPath($media, $storage->getPath($media), 480);
        $this->assertSame([100, 50], array_slice(getimagesize($thumbnail), 0, 2));
    }

    public function testSomeoneElsesWorkKeepsItsCreditAndTellsTheEngines(): void
    {
        $media = $this->service(MediaStorage::class)->import($this->png(), 'portrait.png');
        $media->setCredit('Jane Doe', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Portrait.png');
        $this->service(EntityManagerInterface::class)->flush();

        $this->assertTrue($media->hasCredit());
        $schema = $this->service(MediaSchemaOrgNormalizer::class)->normalize($media, HeadStructuredDataProviderInterface::FORMAT_SCHEMA_ORG);
        $this->assertSame(['@type' => 'Person', 'name' => 'Jane Doe'], $schema['creator']);
        $this->assertSame('https://creativecommons.org/licenses/by-sa/4.0', $schema['license']);
        $this->assertSame('https://commons.wikimedia.org/wiki/File:Portrait.png', $schema['acquireLicensePage']);
        $this->assertFalse($this->service(MediaStorage::class)->import($this->png(), 'own.png')->hasCredit());
    }
}
