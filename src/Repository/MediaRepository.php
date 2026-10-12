<?php

namespace Wexample\SymfonyMedia\Repository;

use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyMedia\Entity\Media;
use Wexample\SymfonyMedia\Entity\MediaFolder;
use Wexample\SymfonyMedia\Entity\Traits\Manipulator\MediaEntityManipulatorTrait;

/**
 * @method Media|null find($id, $lockMode = null, $lockVersion = null)
 */
class MediaRepository extends AbstractRepository
{
    use MediaEntityManipulatorTrait;

    /**
     * @param list<string> $accept as an `accept` attribute: MIME types, `type/*`, `.extension`
     *
     * @return list<Media> the media of $folder, by name, one page of them
     */
    public function findInFolder(
        ?MediaFolder $folder,
        int $offset = 0,
        ?int $limit = null,
        bool $withRestricted = true,
        array $accept = []
    ): array {
        return $this->inFolder($folder, $withRestricted, $accept)
            ->orderBy('media.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countInFolder(
        ?MediaFolder $folder,
        bool $withRestricted = true,
        array $accept = []
    ): int {
        return (int) $this->inFolder($folder, $withRestricted, $accept)
            ->select('COUNT(media.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function inFolder(
        ?MediaFolder $folder,
        bool $withRestricted,
        array $accept = []
    ): QueryBuilder {
        $builder = $this->createQueryBuilder('media');

        if (! $withRestricted) {
            $builder->andWhere('media.restricted = false');
        }

        // Any one of them lets a media in, as the browser reads `accept`.
        $kept = [];
        foreach (array_values($accept) as $index => $entry) {
            $entry = strtolower(trim($entry));

            if ('' === $entry) {
                continue;
            }

            if (str_starts_with($entry, '.')) {
                $kept[] = 'LOWER(media.name) LIKE :accept'.$index;
                $builder->setParameter('accept'.$index, '%'.$entry);
            } elseif (str_ends_with($entry, '/*')) {
                $kept[] = 'media.mimeType LIKE :accept'.$index;
                $builder->setParameter('accept'.$index, substr($entry, 0, -1).'%');
            } else {
                $kept[] = 'media.mimeType = :accept'.$index;
                $builder->setParameter('accept'.$index, $entry);
            }
        }

        if ($kept) {
            $builder->andWhere(implode(' OR ', $kept));
        }

        return null === $folder
            ? $builder->andWhere('media.folder IS NULL')
            : $builder->andWhere('media.folder = :folder')->setParameter('folder', $folder->getId(), UuidType::NAME);
    }
}
