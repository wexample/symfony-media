<?php

namespace Wexample\SymfonyMedia\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Wexample\Pseudocode\Attribute\PseudocodeExport;
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
#[PseudocodeExport(inherited: true)]
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

    /**
     * Who made the file, as their work is to be credited; null for a file of
     * one's own. With the license and where it was taken from, what a page
     * showing someone else's work owes them.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $author = null;

    /**
     * Its short name: `CC BY-SA 4.0`, `Public domain`.
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    protected ?string $license = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $licenseUrl = null;

    /**
     * The page it was taken from: a Wikimedia Commons file, a stock photo.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $sourceUrl = null;

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

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function getLicense(): ?string
    {
        return $this->license;
    }

    public function getLicenseUrl(): ?string
    {
        return $this->licenseUrl;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    /**
     * What is owed to whoever made the file, all at once: a credit is given
     * whole, from one source.
     */
    public function setCredit(
        ?string $author,
        ?string $license,
        ?string $licenseUrl = null,
        ?string $sourceUrl = null
    ): static {
        $this->author = null === $author ? null : mb_substr($author, 0, 255);
        $this->license = null === $license ? null : mb_substr($license, 0, 64);
        $this->licenseUrl = $licenseUrl;
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function hasCredit(): bool
    {
        return null !== $this->author || null !== $this->license || null !== $this->sourceUrl;
    }
}
