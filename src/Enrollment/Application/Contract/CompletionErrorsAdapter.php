<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Contract;

use SovereignStack\Lms\Learning\Application\Contract\CannotCompleteEmptyCourseException;
use SovereignStack\Lms\Learning\Application\Contract\IncompleteModulesException;
use SovereignStack\Lms\Shared\Application\Exception\ConflictException;

/**
 * Bridges the learning completion errors to the enrollment-owned contract so
 * enrollment handlers never import the learning module directly.
 */
final readonly class CompletionErrorsAdapter implements CompletionErrorsInterface
{
    public function emptyCourse(string $versionId): ConflictException
    {
        return CannotCompleteEmptyCourseException::forCourseVersion($versionId);
    }

    public function incompleteModules(array $moduleIds): ConflictException
    {
        return IncompleteModulesException::forModules($moduleIds);
    }
}
