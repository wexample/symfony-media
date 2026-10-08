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
        ?int $limit = null
    ): array {
        return $this->inFolder($folder)
            ->orderBy('media.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countInFolder(?MediaFolder $folder): int
    {
        return (int) $this->inFolder($folder)
            ->select('COUNT(media.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function inFolder(?MediaFolder $folder): QueryBuilder
    {
        $builder = $this->createQueryBuilder('media');

        return null === $folder
            ? $builder->where('media.folder IS NULL')
            : $builder->where('media.folder = :folder')->setParameter('folder', $folder->getId(), UuidType::NAME);
    }
}
