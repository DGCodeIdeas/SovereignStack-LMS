<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\AssignInstructorCommand;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Application\Support\Identifiers;
use SovereignStack\Lms\Course\Domain\Entity\CourseInstructor;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotFoundException;
use SovereignStack\Lms\Course\Domain\Repository\CourseInstructorRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseInstructorId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

final readonly class AssignInstructorHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseRepositoryInterface $courses,
        private CourseInstructorRepositoryInterface $instructors,
        private AuditLoggerInterface $audit,
        private UuidGeneratorInterface $uuid,
        private ClockInterface $clock,
    ) {}

    public function __invoke(AssignInstructorCommand $command): CourseInstructorId
    {
        $courseId = Identifiers::courseId($command->courseId);
        $course = $this->courses->findById($courseId) ?? throw CourseNotFoundException::forId($command->courseId);

        $this->policy->requireAdmin($command->actorUserId, $course);

        $instructorId = CourseInstructorId::fromString($this->uuid->generate());
        $effectiveFrom = $this->parseMoment($command->effectiveFrom, 'effectiveFrom');
        $effectiveUntil = null === $command->effectiveUntil ? null : $this->parseMoment($command->effectiveUntil, 'effectiveUntil');

        try {
            $instructor = CourseInstructor::assign(
                $instructorId,
                $courseId,
                $this->userId($command->instructorUserId, 'instructorUserId'),
                $this->userId($command->actorUserId, 'actorUserId'),
                $this->clock->now(),
                $effectiveFrom,
                $effectiveUntil,
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withErrors(['effectiveUntil' => [$exception->getMessage()]]);
        }

        $this->instructors->save($instructor);

        $this->audit->record(
            'course.instructor_assigned',
            $command->actorUserId,
            $course->organisationId()->value(),
            'course_instructor',
            $instructorId->value(),
            [
                'course_id' => $courseId->value(),
                'instructor_user_id' => $instructor->userId()->value(),
                'effective_from' => $effectiveFrom->format(\DATE_ATOM),
                'effective_until' => $effectiveUntil?->format(\DATE_ATOM),
            ],
        );

        return $instructorId;
    }

    private function parseMoment(string $value, string $field): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw ValidationException::withErrors([$field => [sprintf('Invalid date "%s".', $value)]]);
        }
    }

    private function userId(string $value, string $field): UserIdReference
    {
        try {
            return UserIdReference::fromString($value);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withErrors([$field => [$exception->getMessage()]]);
        }
    }
}
