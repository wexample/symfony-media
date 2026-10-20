<?php

namespace Wexample\SymfonyMedia\Normalizer;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Wexample\SymfonyHelpers\Interface\HeadStructuredDataProviderInterface;
use Wexample\SymfonyMedia\Controller\MediaFileController;
use Wexample\SymfonyMedia\Entity\Media;

/**
 * A media held by a described entity — a project's cover — as the engines
 * read it: an ImageObject, or a MediaObject for anything else, at the address
 * it is served from. A restricted one is left out: nobody outside may open it.
 */
final class MediaSchemaOrgNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Media::class => true];
    }

    public function supportsNormalization(
        mixed $data,
        ?string $format = null,
        array $context = []
    ): bool {
        return HeadStructuredDataProviderInterface::FORMAT_SCHEMA_ORG === $format && $data instanceof Media;
    }

    /**
     * @param Media $data
     */
    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = []
    ): ?array {
        if ($data->isRestricted()) {
            return null;
        }

        return array_filter([
            '@type' => $data->isImage() ? 'ImageObject' : 'MediaObject',
            'contentUrl' => $this->urlGenerator->generate(
                MediaFileController::ROUTE_FILE_NAME,
                ['id' => $data->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'width' => $data->getWidth(),
            'height' => $data->getHeight(),
        ], static fn (mixed $value): bool => null !== $value);
    }
}
