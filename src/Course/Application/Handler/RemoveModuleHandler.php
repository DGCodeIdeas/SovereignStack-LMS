<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\RemoveModuleCommand;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Application\Support\CourseVersionResolver;
use SovereignStack\Lms\Course\Application\Support\Identifiers;
use SovereignStack\Lms\Course\Domain\Exception\CourseModuleNotFoundException;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotFoundException;
use SovereignStack\Lms\Course\Domain\Repository\CourseModuleRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;

final readonly class RemoveModuleHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseVersionResolver $versionResolver,
        private CourseRepositoryInterface $courses,
        private CourseModuleRepositoryInterface $modules,
        private AuditLoggerInterface $audit,
        private TransactionManagerInterface $transactions,
        private ClockInterface $clock,
    ) {}

    public function __invoke(RemoveModuleCommand $command): void
    {
        $courseId = Identifiers::courseId($command->courseId);
        $course = $this->courses->findById($courseId) ?? throw CourseNotFoundException::forId($command->courseId);

        $this->policy->requireEdit($command->actorUserId, $course);

        $version = $this->versionResolver->requireDraft($courseId);
        $moduleId = Identifiers::moduleId($command->moduleId);
        $module = $this->modules->findById($moduleId) ?? throw CourseModuleNotFoundException::forId($command->moduleId);

        if (!$module->courseVersionId()->equals($version->id())) {
            throw CourseModuleNotFoundException::forId($command->moduleId);
        }

        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($version, $moduleId, $now): void {
            $this->modules->delete($moduleId);
            $this->compactPositions($version->id(), $now);
        });

        $this->audit->record(
            'course.module_removed',
            $command->actorUserId,
            $course->organisationId()->value(),
            'course_module',
            $moduleId->value(),
            ['course_id' => $courseId->value(), 'version_id' => $version->id()->value()],
        );
    }

    private function compactPositions(CourseVersionId $versionId, \DateTimeImmutable $now): void
    {
        foreach ($this->modules->listByVersion($versionId) as $index => $module) {
            if ($module->position() === $index) {
                continue;
            }

            $module->changePosition($index, $now);
            $this->modules->save($module);
        }
    }
}
