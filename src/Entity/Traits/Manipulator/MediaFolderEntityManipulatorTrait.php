<?php

namespace Wexample\SymfonyMedia\Entity\Traits\Manipulator;

use Wexample\SymfonyHelpers\Entity\Traits\Manipulator\EntityManipulatorTrait;
use Wexample\SymfonyMedia\Entity\MediaFolder;

trait MediaFolderEntityManipulatorTrait
{
    use EntityManipulatorTrait;

    public static function getEntityClassName(): string
    {
        return MediaFolder::class;
    }
}
