<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Contract;

use SovereignStack\Lms\Shared\Application\Exception\ConflictException;

interface CompletionErrorsInterface
{
    public function emptyCourse(string $versionId): ConflictException;

    /**
     * @param list<string> $moduleIds
     */
    public function incompleteModules(array $moduleIds): ConflictException;
}
