<?php

namespace Wexample\SymfonyMedia\Service;

use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\MimeTypes;
use Wexample\SymfonyMedia\Entity\Media;
use Wexample\SymfonyMedia\Entity\MediaFolder;

/**
 * Where the files of the library lie: `<storage_dir>/<two first characters of
 * the id>/<id>.<extension>`. Out of the public directory, so that every file is
 * read through the access check; spread over subdirectories, so that none
 * holds them all.
 */
class MediaStorage
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MediaThumbnailService $thumbnails,
        #[Autowire(param: 'wexample_symfony_media.storage_dir')]
        private readonly string $storageDir,
    ) {
    }

    /**
     * Takes the file at $path into the library: it is moved, not copied, and
     * becomes a Media of $folder, flushed.
     *
     * @param string $originalName what the file was called where it came from
     */
    public function import(
        string $path,
        string $originalName,
        ?MediaFolder $folder = null
    ): Media {
        $media = new Media($originalName, $folder);
        $media
            ->setMimeType(MimeTypes::getDefault()->guessMimeType($path) ?? 'application/octet-stream')
            ->setSize((int) filesize($path));

        $dimensions = str_starts_with((string) $media->getMimeType(), 'image/') ? @getimagesize($path) : false;
        if (false !== $dimensions) {
            $media->setDimensions($dimensions[0], $dimensions[1]);
        }

        $target = $this->getPath($media);
        if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
            throw new RuntimeException(sprintf('The media directory "%s" cannot be created.', dirname($target)));
        }

        // Across two filesystems — a temporary directory on its own mount —
        // rename cannot work, and the file is copied instead.
        if (! @rename($path, $target) && ! (copy($path, $target) && unlink($path))) {
            throw new RuntimeException(sprintf('The file "%s" cannot be moved into the library.', $path));
        }

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }

    public function getPath(Media $media): string
    {
        return $this->storageDir.'/'.substr((string) $media->getId(), 0, 2).'/'.$media->getStorageName();
    }

    /**
     * Removes the media, its file and its thumbnails. An entity still pointing
     * at it fails the flush: whether to detach it first is the caller's call.
     */
    public function delete(Media $media): void
    {
        $path = $this->getPath($media);

        $this->entityManager->remove($media);
        $this->entityManager->flush();

        if (is_file($path)) {
            unlink($path);
        }
        $this->thumbnails->remove($media);
    }
}
