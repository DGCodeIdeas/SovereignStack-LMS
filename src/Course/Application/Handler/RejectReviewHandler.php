<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\RejectReviewCommand;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Application\Support\CourseVersionResolver;
use SovereignStack\Lms\Course\Application\Support\Identifiers;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotFoundException;
use SovereignStack\Lms\Course\Domain\Exception\CourseVersionNotFoundException;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseVersionRepositoryInterface;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;

final readonly class RejectReviewHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseVersionResolver $versionResolver,
        private CourseRepositoryInterface $courses,
        private CourseVersionRepositoryInterface $versions,
        private AuditLoggerInterface $audit,
    ) {}

    public function __invoke(RejectReviewCommand $command): void
    {
        $courseId = Identifiers::courseId($command->courseId);
        $course = $this->courses->findById($courseId) ?? throw CourseNotFoundException::forId($command->courseId);

        $this->policy->requireAdmin($command->actorUserId, $course);

        $version = $this->versionResolver->latest($courseId) ?? throw CourseVersionNotFoundException::draftNotFound($command->courseId);

        $version->rejectFromReview();
        $this->versions->save($version);

        $this->audit->record(
            'course.rejected_from_review',
            $command->actorUserId,
            $course->organisationId()->value(),
            'course',
            $courseId->value(),
            ['version_id' => $version->id()->value(), 'version_number' => $version->versionNumber()],
        );
    }
}
