<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\CreateDraftVersionCommand;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Application\Support\Identifiers;
use SovereignStack\Lms\Course\Domain\Entity\CourseVersion;
use SovereignStack\Lms\Course\Domain\Enum\CourseVersionStatus;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotFoundException;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseVersionRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ConflictException;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

final readonly class CreateDraftVersionHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseRepositoryInterface $courses,
        private CourseVersionRepositoryInterface $versions,
        private AuditLoggerInterface $audit,
        private UuidGeneratorInterface $uuid,
        private ClockInterface $clock,
    ) {}

    public function __invoke(CreateDraftVersionCommand $command): CourseVersionId
    {
        $courseId = Identifiers::courseId($command->courseId);
        $course = $this->courses->findById($courseId) ?? throw CourseNotFoundException::forId($command->courseId);

        $this->policy->requireEdit($command->actorUserId, $course);

        $existing = $this->versions->listByCourse($courseId);

        foreach ($existing as $version) {
            if (CourseVersionStatus::DRAFT === $version->status()) {
                throw new ConflictException('A draft version already exists for this course.');
            }
        }

        $latest = [] === $existing ? null : $existing[\count($existing) - 1];

        if (null === $latest || !\in_array($latest->status(), [CourseVersionStatus::PUBLISHED, CourseVersionStatus::ARCHIVED], true)) {
            throw new ConflictException('A new draft version requires a published or archived course version.');
        }

        $version = CourseVersion::createDraft(
            CourseVersionId::fromString($this->uuid->generate()),
            $courseId,
            $this->versions->nextVersionNumber($courseId),
            $latest->title(),
            $latest->description(),
            UserIdReference::fromString($command->actorUserId),
            $this->clock->now(),
        );

        $this->versions->save($version);

        $this->audit->record(
            'course.draft_version_created',
            $command->actorUserId,
            $course->organisationId()->value(),
            'course',
            $courseId->value(),
            ['version_id' => $version->id()->value(), 'version_number' => $version->versionNumber()],
        );

        return $version->id();
    }
}
