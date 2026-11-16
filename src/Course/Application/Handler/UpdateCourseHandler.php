<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\UpdateCourseCommand;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Application\Support\CourseVersionResolver;
use SovereignStack\Lms\Course\Application\Support\Identifiers;
use SovereignStack\Lms\Course\Domain\Enum\CourseVisibility;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotFoundException;
use SovereignStack\Lms\Course\Domain\Exception\DuplicateCourseSlugException;
use SovereignStack\Lms\Course\Domain\Exception\InvalidCourseContentException;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseVersionRepositoryInterface;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;

final readonly class UpdateCourseHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseVersionResolver $versionResolver,
        private CourseRepositoryInterface $courses,
        private CourseVersionRepositoryInterface $versions,
        private AuditLoggerInterface $audit,
        private TransactionManagerInterface $transactions,
        private ClockInterface $clock,
    ) {}

    public function __invoke(UpdateCourseCommand $command): void
    {
        $courseId = Identifiers::courseId($command->courseId);
        $course = $this->courses->findById($courseId) ?? throw CourseNotFoundException::forId($command->courseId);

        $this->policy->requireEdit($command->actorUserId, $course);

        $version = $this->versionResolver->requireDraft($courseId);

        $slug = strtolower(trim($command->slug));
        $visibility = $this->visibility($command->visibility);

        if ($slug !== $course->slug()) {
            $existing = $this->courses->findByOrganisationAndSlug($course->organisationId(), $slug);

            if (null !== $existing && !$existing->id()->equals($courseId)) {
                throw DuplicateCourseSlugException::forSlug($course->organisationId()->value(), $slug);
            }
        }

        $now = $this->clock->now();

        try {
            $course->changeSlug($slug, $now);
            $version->updateDetails(trim($command->title), $this->nullableDescription($command->description));
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withErrors(['slug' => [$exception->getMessage()]]);
        } catch (InvalidCourseContentException $exception) {
            throw ValidationException::withErrors(['title' => [$exception->getMessage()]]);
        }

        $course->changeVisibility($visibility, $now);

        $this->transactions->transactional(function () use ($course, $version): void {
            $this->courses->save($course);
            $this->versions->save($version);
        });

        $this->audit->record(
            'course.updated',
            $command->actorUserId,
            $course->organisationId()->value(),
            'course',
            $courseId->value(),
            ['slug' => $slug, 'visibility' => $visibility->value, 'version_id' => $version->id()->value()],
        );
    }

    private function visibility(string $value): CourseVisibility
    {
        try {
            return CourseVisibility::from($value);
        } catch (\ValueError) {
            throw ValidationException::withErrors(['visibility' => [sprintf('Unknown course visibility "%s".', $value)]]);
        }
    }

    private function nullableDescription(string $description): ?string
    {
        $description = trim($description);

        return '' === $description ? null : $description;
    }
}
