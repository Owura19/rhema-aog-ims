<?php

namespace App\Services\Bible;

use RuntimeException;

/** The Bible text (or the tables it would go into) has problems; nothing was written. */
final class KjvImportException extends RuntimeException
{
    /** @param  list<string>  $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(count($problems) . ' problem(s): ' . implode(' ', array_slice($problems, 0, 3)) . (count($problems) > 3 ? ' ...' : ''));
    }
}
