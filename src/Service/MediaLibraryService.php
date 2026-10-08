<?php

namespace Wexample\SymfonyMedia\Service;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyMedia\Controller\MediaFileController;
use Wexample\SymfonyMedia\Entity\Media;
use Wexample\SymfonyMedia\Entity\MediaFolder;
use Wexample\SymfonyMedia\Exception\MediaLibraryException;
use Wexample\SymfonyMedia\Repository\MediaFolderRepository;
use Wexample\SymfonyMedia\Repository\MediaRepository;

/**
 * The library as it is browsed and arranged: a level of it — its folders,
 * then its media, one page at a time —, and the folders made, renamed, moved
 * and deleted.
 *
 * A level is listed in the shape symfony-design-system's file explorer reads:
 * `{ id, path, name, type: directory|file, hasChildren, size, modifiedAt }`,
 * with what a media adds — its type, its address, its thumbnail.
 */
class MediaLibraryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MediaRepository $mediaRepository,
        private readonly MediaFolderRepository $folderRepository,
        private readonly MediaStorage $storage,
        private readonly MediaThumbnailService $thumbnails,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Folders come first, all of them on the first pages; then the media.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function level(
        ?MediaFolder $folder,
        int $offset,
        int $length
    ): array {
        $folders = $this->folderRepository->findChildren($folder);
        $items = array_map($this->describeFolder(...), array_slice($folders, $offset, $length));

        $mediaOffset = max(0, $offset - count($folders));
        $mediaLength = $length - count($items);

        if ($mediaLength > 0) {
            foreach ($this->mediaRepository->findInFolder($folder, $mediaOffset, $mediaLength) as $media) {
                $items[] = $this->describe($media);
            }
        }

        return [
            'items' => $items,
            'total' => count($folders) + $this->mediaRepository->countInFolder($folder),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(Media $media): array
    {
        $thumbnailSizes = $this->thumbnails->getSizes();

        return [
            'id' => (string) $media->getId(),
            'path' => (string) $media->getId(),
            'name' => $media->getName(),
            'type' => 'file',
            'hasChildren' => false,
            'size' => $media->getSize(),
            'modifiedAt' => $media->getDateCreated()?->format(DateTimeInterface::ATOM),
            'mimeType' => $media->getMimeType(),
            'width' => $media->getWidth(),
            'height' => $media->getHeight(),
            'url' => $this->urlGenerator->generate(MediaFileController::ROUTE_FILE_NAME, ['id' => $media->getId()]),
            'thumbnailUrl' => $this->thumbnails->supports($media) && [] !== $thumbnailSizes
                ? $this->urlGenerator->generate(MediaFileController::ROUTE_THUMBNAIL_NAME, [
                    'id' => $media->getId(),
                    'width' => $thumbnailSizes[0],
                ])
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function describeFolder(MediaFolder $folder): array
    {
        return [
            'id' => (string) $folder->getId(),
            'path' => (string) $folder->getId(),
            'name' => $folder->getName(),
            'type' => 'directory',
            'hasChildren' => ! $folder->getChildren()->isEmpty(),
            'size' => 0,
            'modifiedAt' => $folder->getDateCreated()?->format(DateTimeInterface::ATOM),
        ];
    }

    public function createFolder(
        string $name,
        ?MediaFolder $parent = null
    ): MediaFolder {
        $folder = new MediaFolder($this->cleanName($name), $parent);

        $this->entityManager->persist($folder);
        $this->entityManager->flush();

        return $folder;
    }

    public function rename(
        Media|MediaFolder $item,
        string $name
    ): void {
        $item->setName($this->cleanName($name));
        $this->entityManager->flush();
    }

    /**
     * @throws MediaLibraryException when a folder would go inside itself
     */
    public function move(
        Media|MediaFolder $item,
        ?MediaFolder $target
    ): void {
        if ($item instanceof MediaFolder) {
            if (null !== $target && $item->contains($target)) {
                throw new MediaLibraryException(sprintf('The folder "%s" cannot go inside itself.', $item->getName()));
            }

            $item->setParent($target);
        } else {
            $item->setFolder($target);
        }

        $this->entityManager->flush();
    }

    /**
     * @throws MediaLibraryException when the folder still holds something
     */
    public function delete(Media|MediaFolder $item): void
    {
        if ($item instanceof Media) {
            $this->storage->delete($item);

            return;
        }

        if (! $item->isEmpty()) {
            throw new MediaLibraryException(sprintf('The folder "%s" is not empty.', $item->getName()));
        }

        $this->entityManager->remove($item);
        $this->entityManager->flush();
    }

    /**
     * @throws MediaLibraryException when nothing is left of the name
     */
    private function cleanName(string $name): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '');

        if ('' === $name) {
            throw new MediaLibraryException('A name is needed.');
        }

        return mb_substr($name, 0, 255);
    }
}
