<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\ArchiveCourseCommand;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Application\Support\Identifiers;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotFoundException;
use SovereignStack\Lms\Course\Domain\Exception\CourseVersionNotFoundException;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseVersionRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;

final readonly class ArchiveCourseHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseRepositoryInterface $courses,
        private CourseVersionRepositoryInterface $versions,
        private AuditLoggerInterface $audit,
        private TransactionManagerInterface $transactions,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ArchiveCourseCommand $command): void
    {
        $courseId = Identifiers::courseId($command->courseId);
        $course = $this->courses->findById($courseId) ?? throw CourseNotFoundException::forId($command->courseId);

        $this->policy->requireAdmin($command->actorUserId, $course);

        $publishedVersionId = $course->currentPublishedVersionId()
            ?? throw CourseVersionNotFoundException::publishedNotFound($courseId->value());

        $version = $this->versions->findById(CourseVersionId::fromString($publishedVersionId))
            ?? throw CourseVersionNotFoundException::forId($publishedVersionId);

        $version->archive();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($course, $version, $now): void {
            $this->versions->save($version);
            $course->clearPublishedVersion($now);
            $this->courses->clearPublishedVersion($course);
        });

        $this->audit->record(
            'course.archived',
            $command->actorUserId,
            $course->organisationId()->value(),
            'course',
            $courseId->value(),
            ['version_id' => $version->id()->value(), 'version_number' => $version->versionNumber()],
        );
    }
}
