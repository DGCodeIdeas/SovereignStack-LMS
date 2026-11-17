<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Security;

use SovereignStack\Lms\Course\Application\Contract\PermissionCheckerInterface;
use SovereignStack\Lms\Course\Domain\Entity\Course;
use SovereignStack\Lms\Course\Domain\Repository\CourseInstructorRepositoryInterface;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;

/**
 * Central authorisation rules for course authoring operations.
 *
 * Editing is granted to the course owner, an assigned instructor or an
 * administrator holding user.manage scoped to the course organisation.
 */
final readonly class CourseAccessPolicy
{
    private const ADMIN_PERMISSION = 'user.manage';

    public function __construct(
        private PermissionCheckerInterface $permissions,
        private CourseInstructorRepositoryInterface $instructors,
        private ClockInterface $clock,
    ) {}

    public function requireEdit(string $actorUserId, Course $course): void
    {
        $organisationId = $course->organisationId()->value();

        if ($this->isOwner($actorUserId, $course)) {
            return;
        }

        if ($this->isInstructor($actorUserId, $course)) {
            return;
        }

        if ($this->permissions->can($actorUserId, self::ADMIN_PERMISSION, $organisationId)) {
            return;
        }

        throw new ForbiddenException('You are not allowed to edit this course.');
    }

    public function requireAdmin(string $actorUserId, Course $course): void
    {
        $this->permissions->require($actorUserId, self::ADMIN_PERMISSION, $course->organisationId()->value());
    }

    public function requireCreate(string $actorUserId, string $organisationId): void
    {
        $this->permissions->require($actorUserId, 'course.create', $organisationId);
    }

    public function requireList(string $actorUserId, string $organisationId): void
    {
        if ($this->permissions->can($actorUserId, 'course.update', $organisationId)) {
            return;
        }

        if ($this->permissions->can($actorUserId, self::ADMIN_PERMISSION, $organisationId)) {
            return;
        }

        throw new ForbiddenException('You are not allowed to list courses for this organisation.');
    }

    public function requireView(string $actorUserId, Course $course): void
    {
        try {
            $this->requireEdit($actorUserId, $course);
        } catch (ForbiddenException $exception) {
            if (!$this->permissions->can($actorUserId, 'course.view', $course->organisationId()->value())) {
                throw $exception;
            }
        }
    }

    private function isOwner(string $actorUserId, Course $course): bool
    {
        return $course->ownerUserId()->value() === strtolower(trim($actorUserId));
    }

    private function isInstructor(string $actorUserId, Course $course): bool
    {
        try {
            $actor = UserIdReference::fromString($actorUserId);
        } catch (\InvalidArgumentException) {
            return false;
        }

        $instructor = $this->instructors->findByCourseAndUser($course->id(), $actor);

        return null !== $instructor && $instructor->isEffectiveAt($this->clock->now());
    }
}
