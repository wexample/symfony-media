<?php

namespace Wexample\SymfonyMedia\Tests\Integration;

use Wexample\SymfonyMedia\Exception\MediaLibraryException;
use Wexample\SymfonyMedia\Service\MediaLibraryService;
use Wexample\SymfonyMedia\Service\MediaStorage;

class LibraryTest extends AbstractMediaTestCase
{
    public function testALevelListsItsFoldersFirstThenItsMediaPageByPage(): void
    {
        $library = $this->service(MediaLibraryService::class);
        $storage = $this->service(MediaStorage::class);

        $photos = $library->createFolder('Photos');
        $library->createFolder('Archives');
        $library->createFolder('Inside', $photos);
        $storage->import($this->png(), 'b.png');
        $storage->import($this->text(), 'a.txt');
        $storage->import($this->png(), 'inside.png', $photos);

        $level = $library->level(null, 0, 3);
        $this->assertSame(4, $level['total']);
        $this->assertSame(['Archives', 'Photos', 'a.txt'], array_column($level['items'], 'name'));
        $this->assertSame(['directory', 'directory', 'file'], array_column($level['items'], 'type'));
        $this->assertTrue($level['items'][1]['hasChildren']);
        $this->assertNull($level['items'][2]['thumbnailUrl']);

        $next = $library->level(null, 3, 3);
        $this->assertSame(['b.png'], array_column($next['items'], 'name'));
        $this->assertStringContainsString('/thumbnail/160', $next['items'][0]['thumbnailUrl']);
        $this->assertStringStartsWith('/_media/', $next['items'][0]['url']);

        $inner = $library->level($photos, 0, 10)['items'];
        $this->assertSame(['Inside', 'inside.png'], array_column($inner, 'name'));
        $this->assertSame($photos->getId().'/'.$inner[0]['id'], $inner[0]['path']);
        $this->assertSame($photos->getId().'/'.$inner[1]['id'], $inner[1]['path']);
    }

    public function testAFolderNeitherGoesInsideItselfNorLeavesWhileFull(): void
    {
        $library = $this->service(MediaLibraryService::class);
        $photos = $library->createFolder('Photos');
        $inside = $library->createFolder('Inside', $photos);

        try {
            $library->move($photos, $inside);
            $this->fail('A folder went inside itself.');
        } catch (MediaLibraryException) {
        }

        $this->expectException(MediaLibraryException::class);
        $library->delete($photos);
    }

    public function testANameIsNeeded(): void
    {
        $this->expectException(MediaLibraryException::class);
        $this->service(MediaLibraryService::class)->createFolder("  \t ");
    }
}
