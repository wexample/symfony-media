<?php

namespace Wexample\SymfonyMedia\Repository;

use Symfony\Bridge\Doctrine\Types\UuidType;
use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyMedia\Entity\MediaFolder;
use Wexample\SymfonyMedia\Entity\Traits\Manipulator\MediaFolderEntityManipulatorTrait;

/**
 * @method MediaFolder|null find($id, $lockMode = null, $lockVersion = null)
 */
class MediaFolderRepository extends AbstractRepository
{
    use MediaFolderEntityManipulatorTrait;

    /**
     * @return list<MediaFolder> the folders right under $parent, by name
     */
    public function findChildren(?MediaFolder $parent): array
    {
        $builder = $this->createQueryBuilder('folder')->orderBy('folder.name', 'ASC');

        null === $parent
            ? $builder->where('folder.parent IS NULL')
            : $builder->where('folder.parent = :parent')->setParameter('parent', $parent->getId(), UuidType::NAME);

        return $builder->getQuery()->getResult();
    }
}
