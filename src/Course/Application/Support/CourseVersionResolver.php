<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Support;

use SovereignStack\Lms\Course\Domain\Entity\CourseVersion;
use SovereignStack\Lms\Course\Domain\Exception\CourseVersionNotFoundException;
use SovereignStack\Lms\Course\Domain\Repository\CourseVersionRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;

/**
 * Locates the course version a write operation targets.
 *
 * Content mutations only ever apply to the draft. When no draft exists the
 * latest version is inspected so a published, archived or in-review course is
 * rejected with the domain's not-editable error rather than a generic
 * "draft not found" result.
 */
final readonly class CourseVersionResolver
{
    public function __construct(
        private CourseVersionRepositoryInterface $versions,
    ) {}

    public function requireDraft(CourseId $courseId): CourseVersion
    {
        $draft = $this->versions->findDraftByCourse($courseId);

        if (null !== $draft) {
            return $draft;
        }

        $latest = $this->latest($courseId);

        if (null === $latest) {
            throw CourseVersionNotFoundException::draftNotFound($courseId->value());
        }

        $latest->assertEditable();

        throw CourseVersionNotFoundException::draftNotFound($courseId->value());
    }

    public function latest(CourseId $courseId): ?CourseVersion
    {
        $versions = $this->versions->listByCourse($courseId);

        if ([] === $versions) {
            return null;
        }

        return $versions[\count($versions) - 1];
    }
}
