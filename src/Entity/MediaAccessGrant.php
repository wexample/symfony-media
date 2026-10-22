<?php

namespace Wexample\SymfonyMedia\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Traits\HasDateCreatedTrait;
use Wexample\SymfonyMedia\Repository\MediaAccessGrantRepository;

/**
 * A right to download one media without being let into the library: until a
 * date, a number of times, or both. Its link is signed (MediaAccessService);
 * the row is what counts the downloads, which a signature alone cannot.
 *
 * `origin` says what gave it, in the giver's words — "shop_cart_item:<id>".
 */
#[ORM\Entity(repositoryClass: MediaAccessGrantRepository::class)]
#[ORM\Table(name: 'media_access_grant')]
#[ORM\Index(columns: ['origin'])]
class MediaAccessGrant extends AbstractEntity
{
    use HasDateCreatedTrait;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected Media $media;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    protected ?int $maxUses = null;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $uses = 0;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $origin = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $dateLastUsed = null;

    public function __construct(
        Media $media,
        ?DateTimeImmutable $expiresAt = null,
        ?int $maxUses = null,
        ?string $origin = null,
    ) {
        parent::__construct();

        $this->media = $media;
        $this->expiresAt = $expiresAt;
        $this->maxUses = $maxUses;
        $this->origin = $origin;
        $this->setDateCreatedNow();
    }

    public function getMedia(): Media
    {
        return $this->media;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getMaxUses(): ?int
    {
        return $this->maxUses;
    }

    public function getUses(): int
    {
        return $this->uses;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function getDateLastUsed(): ?DateTimeImmutable
    {
        return $this->dateLastUsed;
    }

    public function isUsable(DateTimeImmutable $now = new DateTimeImmutable()): bool
    {
        return (null === $this->expiresAt || $now < $this->expiresAt)
            && (null === $this->maxUses || $this->uses < $this->maxUses);
    }

    public function use(DateTimeImmutable $now = new DateTimeImmutable()): static
    {
        ++$this->uses;
        $this->dateLastUsed = $now;

        return $this;
    }
}
