<?php

namespace App\Exceptions;

use RuntimeException;

/** A delete, approval or restore the recycle-bin rules don't allow; its message is safe to show. */
class DeletionRefused extends RuntimeException
{
}
