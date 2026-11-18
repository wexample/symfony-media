<?php

namespace Wexample\SymfonyMedia\Api\Dto;

use Wexample\SymfonyApi\Api\Dto\AbstractEntityDto;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyMedia\Entity\Media;

/**
 * A file of the library as a screen reads it. Where it is served from is
 * not the entity's: DefaultMediaNormalizer adds it to the item's metadata.
 */
class MediaDto extends AbstractEntityDto
{
    public string $name;

    public string $extension;

    public ?string $mimeType;

    public int $size;

    public ?int $width;

    public ?int $height;

    public bool $restricted;

    public ?string $author;

    public ?string $license;

    public ?string $licenseUrl;

    public ?string $sourceUrl;

    /**
     * @param Media $entity
     */
    public static function fromEntity(AbstractEntity $entity): self
    {
        $dto = parent::fromEntity($entity);

        $dto->name = $entity->getName();
        $dto->extension = $entity->getExtension();
        $dto->mimeType = $entity->getMimeType();
        $dto->size = $entity->getSize();
        $dto->width = $entity->getWidth();
        $dto->height = $entity->getHeight();
        $dto->restricted = $entity->isRestricted();
        $dto->author = $entity->getAuthor();
        $dto->license = $entity->getLicense();
        $dto->licenseUrl = $entity->getLicenseUrl();
        $dto->sourceUrl = $entity->getSourceUrl();

        return $dto;
    }
}
