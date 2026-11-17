<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Application\Contract;

use SovereignStack\Lms\Shared\Application\Exception\ConflictException;

final class IncompleteModulesException extends ConflictException
{
    /**
     * @param list<string> $moduleIds
     */
    public static function forModules(array $moduleIds): self
    {
        return new self(sprintf('Required modules are incomplete: %s.', implode(', ', $moduleIds)));
    }
}
