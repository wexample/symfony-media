<?php

namespace Wexample\SymfonyMedia\Api\Normalizer\Entity\Media;

use ArrayObject;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Interface\NormalizableDataInterface;
use Wexample\SymfonyHelpers\Normalizer\AbstractEntityNormalizer;
use Wexample\SymfonyMedia\Api\Dto\MediaDto;
use Wexample\SymfonyMedia\Controller\MediaFileController;
use Wexample\SymfonyMedia\Entity\Media;
use Wexample\SymfonyMedia\Entity\Traits\Manipulator\MediaEntityManipulatorTrait;
use Wexample\SymfonyMedia\Service\MediaThumbnailService;

/**
 * A media and, in its metadata, where it is served from: `url`, the file
 * behind its access check, and `thumbnails`, the url of each width it is
 * drawn at — none for a file that has no thumbnail.
 */
class DefaultMediaNormalizer extends AbstractEntityNormalizer
{
    use MediaEntityManipulatorTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MediaThumbnailService $thumbnails,
    ) {
    }

    public function normalizeEntity(
        Media|AbstractEntity $entity,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|ArrayObject|NormalizableDataInterface|null {
        return MediaDto::fromEntity($entity);
    }

    protected function normalizeMetadata(
        Media|AbstractEntity $entity,
        ?string $format = null,
        array $context = []
    ): array {
        $thumbnails = [];

        if ($this->thumbnails->supports($entity)) {
            foreach ($this->thumbnails->getSizes() as $width) {
                $thumbnails[(string) $width] = $this->urlGenerator->generate(MediaFileController::ROUTE_THUMBNAIL_NAME, [
                    'id' => $entity->getId(),
                    'width' => $width,
                ]);
            }
        }

        return [
            'url' => $this->urlGenerator->generate(MediaFileController::ROUTE_FILE_NAME, ['id' => $entity->getId()]),
            'thumbnails' => $thumbnails,
        ];
    }
}
