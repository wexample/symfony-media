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
     * @return list<Media> the media of $folder, by name, one page of them
     */
    public function findInFolder(
        ?MediaFolder $folder,
        int $offset = 0,
        ?int $limit = null,
        bool $withRestricted = true
    ): array {
        return $this->inFolder($folder, $withRestricted)
            ->orderBy('media.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countInFolder(
        ?MediaFolder $folder,
        bool $withRestricted = true
    ): int {
        return (int) $this->inFolder($folder, $withRestricted)
            ->select('COUNT(media.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function inFolder(
        ?MediaFolder $folder,
        bool $withRestricted
    ): QueryBuilder {
        $builder = $this->createQueryBuilder('media');

        if (! $withRestricted) {
            $builder->andWhere('media.restricted = false');
        }

        return null === $folder
            ? $builder->andWhere('media.folder IS NULL')
            : $builder->andWhere('media.folder = :folder')->setParameter('folder', $folder->getId(), UuidType::NAME);
    }
}
