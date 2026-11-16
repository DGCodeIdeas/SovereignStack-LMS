<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\CreateCourseCommand;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Domain\Entity\Course;
use SovereignStack\Lms\Course\Domain\Entity\CourseVersion;
use SovereignStack\Lms\Course\Domain\Enum\CourseVisibility;
use SovereignStack\Lms\Course\Domain\Exception\DuplicateCourseSlugException;
use SovereignStack\Lms\Course\Domain\Exception\InvalidCourseContentException;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseVersionRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

final readonly class CreateCourseHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseRepositoryInterface $courses,
        private CourseVersionRepositoryInterface $versions,
        private AuditLoggerInterface $audit,
        private TransactionManagerInterface $transactions,
        private UuidGeneratorInterface $uuid,
        private ClockInterface $clock,
    ) {}

    public function __invoke(CreateCourseCommand $command): CourseId
    {
        $this->policy->requireCreate($command->actorUserId, $command->organisationId);

        $organisationId = $this->organisationId($command->organisationId);
        $owner = $this->userId($command->actorUserId);
        $slug = strtolower(trim($command->slug));

        if (null !== $this->courses->findByOrganisationAndSlug($organisationId, $slug)) {
            throw DuplicateCourseSlugException::forSlug($organisationId->value(), $slug);
        }

        $now = $this->clock->now();
        $courseId = CourseId::fromString($this->uuid->generate());
        $versionId = CourseVersionId::fromString($this->uuid->generate());

        try {
            $course = Course::create($courseId, $organisationId, $owner, $slug, CourseVisibility::ORGANISATION_ONLY, $now);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withErrors(['slug' => [$exception->getMessage()]]);
        }

        try {
            $version = CourseVersion::createDraft(
                $versionId,
                $courseId,
                1,
                trim($command->title),
                $this->nullableDescription($command->description),
                $owner,
                $now,
            );
        } catch (InvalidCourseContentException $exception) {
            throw ValidationException::withErrors(['title' => [$exception->getMessage()]]);
        }

        $this->transactions->transactional(function () use ($course, $version): void {
            $this->courses->save($course);
            $this->versions->save($version);
        });

        $this->audit->record(
            'course.created',
            $command->actorUserId,
            $organisationId->value(),
            'course',
            $courseId->value(),
            ['slug' => $slug, 'version_id' => $versionId->value(), 'version_number' => 1],
        );

        return $courseId;
    }

    private function organisationId(string $value): OrganisationIdReference
    {
        try {
            return OrganisationIdReference::fromString($value);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withErrors(['organisationId' => [$exception->getMessage()]]);
        }
    }

    private function userId(string $value): UserIdReference
    {
        try {
            return UserIdReference::fromString($value);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withErrors(['actorUserId' => [$exception->getMessage()]]);
        }
    }

    private function nullableDescription(string $description): ?string
    {
        $description = trim($description);

        return '' === $description ? null : $description;
    }
}
