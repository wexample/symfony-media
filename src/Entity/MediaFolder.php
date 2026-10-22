<?php

namespace Wexample\SymfonyMedia\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Traits\HasDateCreatedTrait;
use Wexample\SymfonyMedia\Repository\MediaFolderRepository;

/**
 * A folder of the library. Folders nest; one without a parent sits at the
 * root. A folder holding anything is not deleted (MediaLibraryService).
 */
#[ORM\Entity(repositoryClass: MediaFolderRepository::class)]
#[ORM\Table(name: 'media_folder')]
class MediaFolder extends AbstractEntity implements Stringable
{
    use HasDateCreatedTrait;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $name;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true)]
    protected ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $children;

    /**
     * @var Collection<int, Media>
     */
    #[ORM\OneToMany(mappedBy: 'folder', targetEntity: Media::class)]
    protected Collection $media;

    public function __construct(
        string $name,
        ?self $parent = null
    ) {
        parent::__construct();

        $this->name = $name;
        $this->children = new ArrayCollection();
        $this->media = new ArrayCollection();
        $this->setParent($parent);
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

    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * Both sides kept in step, so that a folder reads right before the flush.
     */
    public function setParent(?self $parent): static
    {
        $this->parent?->children->removeElement($this);
        $this->parent = $parent;
        $parent?->children->add($this);

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * @return Collection<int, Media>
     */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    /**
     * Kept in step by Media::setFolder(), the owning side.
     *
     * @internal
     */
    public function syncMedia(Media $media, bool $in): void
    {
        $in ? $this->media->add($media) : $this->media->removeElement($media);
    }

    public function isEmpty(): bool
    {
        return $this->children->isEmpty() && $this->media->isEmpty();
    }

    /**
     * Whether $folder is this one or lies under it: what a move must refuse,
     * since a folder put inside itself loops.
     */
    public function contains(self $folder): bool
    {
        for ($current = $folder; null !== $current; $current = $current->getParent()) {
            if ($current === $this) {
                return true;
            }
        }

        return false;
    }
}
