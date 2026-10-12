<?php

namespace Wexample\SymfonyMedia\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Traits\HasDateCreatedTrait;
use Wexample\SymfonyHelpers\Entity\Traits\HasMimeTypeTrait;
use Wexample\SymfonyMedia\Repository\MediaRepository;

/**
 * A file of the library, kept for itself: an entity using it points at it with
 * a plain relation — a cover as a ManyToOne, attachments as a ManyToMany —, and
 * the same media may serve in several places.
 *
 * The file lies under the storage directory, named after the id
 * (MediaStorage): what a person called it is `name`, free to change, and
 * `originalName` is what it was called when it arrived.
 */
#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'media')]
#[ORM\Index(columns: ['name'])]
class Media extends AbstractEntity implements Stringable
{
    use HasDateCreatedTrait;
    use HasMimeTypeTrait;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $name;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $originalName;

    /**
     * Lowercase, without the dot; empty when the file had none.
     */
    #[ORM\Column(type: Types::STRING, length: 16)]
    protected string $extension = '';

    #[ORM\Column(type: Types::BIGINT)]
    protected int $size = 0;

    /**
     * Pixels, for an image GD could read; null otherwise.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    protected ?int $width = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    protected ?int $height = null;

    /**
     * Null at the root of the library.
     */
    /** Read by the managers of the library only, and through access grants (MediaAccessService): a file sold, a private document. */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    protected bool $restricted = false;

    #[ORM\ManyToOne(targetEntity: MediaFolder::class, inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: true)]
    protected ?MediaFolder $folder = null;

    public function __construct(
        string $originalName,
        ?MediaFolder $folder = null
    ) {
        parent::__construct();

        $this->originalName = $originalName;
        $this->name = $originalName;
        $this->extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $this->setFolder($folder);
        $this->setDateCreatedNow();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * The name of the stored file: the id, so that two media never collide and
     * nothing a person typed reaches the disk.
     */
    public function getStorageName(): string
    {
        return (string) $this->getId().('' !== $this->extension ? '.'.$this->extension : '');
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setDimensions(?int $width, ?int $height): static
    {
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    public function isImage(): bool
    {
        return null !== $this->width;
    }

    public function getFolder(): ?MediaFolder
    {
        return $this->folder;
    }

    public function setFolder(?MediaFolder $folder): static
    {
        $this->folder?->syncMedia($this, false);
        $this->folder = $folder;
        $folder?->syncMedia($this, true);

        return $this;
    }

    public function isRestricted(): bool
    {
        return $this->restricted;
    }

    public function setRestricted(bool $restricted): static
    {
        $this->restricted = $restricted;

        return $this;
    }
}
