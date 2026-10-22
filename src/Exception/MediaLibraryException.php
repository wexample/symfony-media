<?php

namespace Wexample\SymfonyMedia\Exception;

use RuntimeException;

/**
 * What the library refuses to do — delete a folder that still holds
 * something, move a folder into itself —, said to whoever asked.
 */
class MediaLibraryException extends RuntimeException
{
}
