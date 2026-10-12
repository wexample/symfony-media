<?php

namespace Wexample\SymfonyMedia\Repository;

use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyMedia\Entity\MediaAccessGrant;
use Wexample\SymfonyMedia\Entity\Traits\Manipulator\MediaAccessGrantEntityManipulatorTrait;

/**
 * @method MediaAccessGrant|null find($id, $lockMode = null, $lockVersion = null)
 */
class MediaAccessGrantRepository extends AbstractRepository
{
    use MediaAccessGrantEntityManipulatorTrait;

    /**
     * @return list<MediaAccessGrant> what one giver gave, oldest first
     */
    public function findByOrigin(string $origin): array
    {
        return $this->findBy(['origin' => $origin], ['dateCreated' => 'ASC']);
    }
}
