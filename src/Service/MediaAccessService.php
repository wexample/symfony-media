<?php

namespace Wexample\SymfonyMedia\Service;

use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyMedia\Controller\MediaFileController;
use Wexample\SymfonyMedia\Entity\Media;
use Wexample\SymfonyMedia\Entity\MediaAccessGrant;

/**
 * Downloads given outside the library's roles: a grant per right, reached
 * through a signed link that works until the grant expires, and counted.
 */
class MediaAccessService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UriSigner $uriSigner,
    ) {
    }

    /**
     * Persisted, flushed by the caller with what gave it.
     */
    public function grant(
        Media $media,
        ?DateTimeImmutable $expiresAt = null,
        ?int $maxUses = null,
        ?string $origin = null,
    ): MediaAccessGrant {
        $grant = new MediaAccessGrant($media, $expiresAt, $maxUses, $origin);
        $this->entityManager->persist($grant);

        return $grant;
    }

    /**
     * The link to send, absolute: signed, and refused once the grant expires.
     */
    public function getUrl(MediaAccessGrant $grant): string
    {
        $url = $this->urlGenerator->generate(
            MediaFileController::ROUTE_GRANT_NAME,
            ['id' => (string) $grant->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->uriSigner->sign($url, $grant->getExpiresAt());
    }

    /**
     * Counts one download if the grant still allows it. The row is locked so
     * two downloads at once cannot both take the last use.
     */
    public function consume(MediaAccessGrant $grant): bool
    {
        return $this->entityManager->wrapInTransaction(function () use ($grant): bool {
            $this->entityManager->lock($grant, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($grant);

            if (! $grant->isUsable()) {
                return false;
            }

            $grant->use();

            return true;
        });
    }
}
