<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Exception;

use InvalidArgumentException;

final class InvalidPurge extends InvalidArgumentException implements SlugHistoryException
{
    public static function negative(int $days): self
    {
        return new self(sprintf(
            'The number of days cannot be negative, %d given. Purging would then clear the whole history.',
            $days,
        ));
    }
}
