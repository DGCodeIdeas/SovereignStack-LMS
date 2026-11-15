<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\AddModuleCommand;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Application\Support\CourseVersionResolver;
use SovereignStack\Lms\Course\Application\Support\Identifiers;
use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotFoundException;
use SovereignStack\Lms\Course\Domain\Exception\InvalidCourseContentException;
use SovereignStack\Lms\Course\Domain\Repository\CourseModuleRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;

final readonly class AddModuleHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseVersionResolver $versionResolver,
        private CourseRepositoryInterface $courses,
        private CourseModuleRepositoryInterface $modules,
        private AuditLoggerInterface $audit,
        private UuidGeneratorInterface $uuid,
        private ClockInterface $clock,
    ) {}

    public function __invoke(AddModuleCommand $command): CourseModuleId
    {
        $courseId = Identifiers::courseId($command->courseId);
        $course = $this->courses->findById($courseId) ?? throw CourseNotFoundException::forId($command->courseId);

        $this->policy->requireEdit($command->actorUserId, $course);

        $version = $this->versionResolver->requireDraft($courseId);
        $moduleId = CourseModuleId::fromString($this->uuid->generate());

        try {
            $module = CourseModule::create(
                $moduleId,
                $version->id(),
                trim($command->title),
                $this->nullableDescription($command->description),
                $this->modules->nextPosition($version->id()),
                $command->isRequired,
                $this->clock->now(),
            );
        } catch (InvalidCourseContentException $exception) {
            throw ValidationException::withErrors(['module' => [$exception->getMessage()]]);
        }

        $this->modules->save($module);

        $this->audit->record(
            'course.module_added',
            $command->actorUserId,
            $course->organisationId()->value(),
            'course_module',
            $moduleId->value(),
            ['course_id' => $courseId->value(), 'version_id' => $version->id()->value(), 'position' => $module->position()],
        );

        return $moduleId;
    }

    private function nullableDescription(string $description): ?string
    {
        $description = trim($description);

        return '' === $description ? null : $description;
    }
}
