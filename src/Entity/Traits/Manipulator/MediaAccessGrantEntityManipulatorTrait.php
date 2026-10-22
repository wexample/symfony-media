<?php

namespace Wexample\SymfonyMedia\Entity\Traits\Manipulator;

use Wexample\SymfonyHelpers\Entity\Traits\Manipulator\EntityManipulatorTrait;
use Wexample\SymfonyMedia\Entity\MediaAccessGrant;

trait MediaAccessGrantEntityManipulatorTrait
{
    use EntityManipulatorTrait;

    public static function getEntityClassName(): string
    {
        return MediaAccessGrant::class;
    }
}
