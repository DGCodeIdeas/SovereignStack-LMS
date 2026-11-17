<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Application\Contract;

interface CompletionErrorsInterface
{
    public function emptyCourse(string $versionId): CannotCompleteEmptyCourseException;

    /**
     * @param list<string> $moduleIds
     */
    public function incompleteModules(array $moduleIds): IncompleteModulesException;
}
