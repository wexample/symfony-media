<?php

namespace Wexample\SymfonyMedia\Service;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Wexample\SymfonyMedia\Entity\Media;

/**
 * Smaller copies of an image, drawn the first time they are asked for and
 * kept beside the library: `<storage_dir>/thumbnails/<width>/<id>.webp`. The
 * original is never touched. Only the configured widths are drawn.
 *
 * GD reads jpeg, png, gif and webp; anything else has no thumbnail and a
 * screen shows its icon.
 */
class MediaThumbnailService
{
    /**
     * The largest image drawn, in pixels. GD decodes the whole picture before
     * scaling it, at about four bytes a pixel: fifty million pixels already
     * take some 200 MB. A file whose header announces more — a photo too large
     * as much as a few kilobytes of png claiming to be a billion pixels — has
     * no thumbnail instead of exhausting the worker's memory.
     */
    public const int MAX_PIXELS = 50_000_000;

    /**
     * @param list<int> $sizes
     */
    public function __construct(
        #[Autowire(param: 'wexample_symfony_media.storage_dir')]
        private readonly string $storageDir,
        #[Autowire(param: 'wexample_symfony_media.thumbnail_sizes')]
        private readonly array $sizes,
    ) {
    }

    /**
     * @return list<int>
     */
    public function getSizes(): array
    {
        $sizes = $this->sizes;
        sort($sizes);

        return $sizes;
    }

    public function supports(Media $media): bool
    {
        return $media->isImage()
            && in_array($media->getMimeType(), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)
            // The dimensions stored are the file's own header, read when it
            // was taken in (MediaStorage), which is what GD would allocate for.
            && (int) $media->getWidth() * (int) $media->getHeight() <= self::MAX_PIXELS;
    }

    /**
     * The thumbnail of $media at $width, drawn now if it was not yet; null
     * when the media has none.
     *
     * @param string $source the path of the original
     *
     * @throws InvalidArgumentException when $width is not one of the configured sizes
     */
    public function getPath(
        Media $media,
        string $source,
        int $width
    ): ?string {
        if (! in_array($width, $this->sizes, true)) {
            throw new InvalidArgumentException(sprintf('No thumbnail is drawn %d pixels wide: %s only.', $width, implode(', ', $this->getSizes())));
        }

        if (! $this->supports($media)) {
            return null;
        }

        $path = $this->thumbnailPath($media, $width);
        if (! is_file($path)) {
            $this->draw($source, $path, $width);
        }

        return $path;
    }

    public function remove(Media $media): void
    {
        foreach ($this->sizes as $width) {
            $path = $this->thumbnailPath($media, $width);

            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function thumbnailPath(
        Media $media,
        int $width
    ): string {
        return $this->storageDir.'/thumbnails/'.$width.'/'.$media->getId().'.webp';
    }

    /**
     * Scaled to $width, never up, its ratio kept.
     */
    private function draw(
        string $source,
        string $target,
        int $width
    ): void {
        $image = imagecreatefromstring((string) file_get_contents($source));
        if (false === $image) {
            throw new RuntimeException(sprintf('The image "%s" cannot be read.', $source));
        }

        if (imagesx($image) > $width) {
            $scaled = imagescale($image, $width);
            $image = false !== $scaled ? $scaled : $image;
        }

        // Kept transparent where the original was.
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        // Two thumbnails asked at once may both find the directory missing.
        if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
            throw new RuntimeException(sprintf('The thumbnail directory "%s" cannot be created.', dirname($target)));
        }

        // Drawn beside the thumbnail and moved in place once whole: a request
        // finding the file, which getPath() takes as drawn, never reads one
        // half-written, nor one a crash left truncated.
        $temporary = $target.'.'.bin2hex(random_bytes(6)).'.part';

        if (! imagewebp($image, $temporary, 80) || ! rename($temporary, $target)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            throw new RuntimeException(sprintf('The thumbnail "%s" cannot be written.', $target));
        }
    }
}
