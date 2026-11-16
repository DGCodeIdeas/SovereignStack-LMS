<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\ReorderModulesCommand;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Application\Support\CourseVersionResolver;
use SovereignStack\Lms\Course\Application\Support\Identifiers;
use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotFoundException;
use SovereignStack\Lms\Course\Domain\Repository\CourseModuleRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;

final readonly class ReorderModulesHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseVersionResolver $versionResolver,
        private CourseRepositoryInterface $courses,
        private CourseModuleRepositoryInterface $modules,
        private AuditLoggerInterface $audit,
        private TransactionManagerInterface $transactions,
    ) {}

    /**
     * @return list<string>
     */
    public function __invoke(ReorderModulesCommand $command): array
    {
        $courseId = Identifiers::courseId($command->courseId);
        $course = $this->courses->findById($courseId) ?? throw CourseNotFoundException::forId($command->courseId);

        $this->policy->requireEdit($command->actorUserId, $course);

        $version = $this->versionResolver->requireDraft($courseId);

        $moduleIds = array_map(
            static fn (string $id): CourseModuleId => Identifiers::moduleId($id),
            array_values($command->moduleIds),
        );

        $stored = $this->modules->listByVersion($version->id());
        $this->assertExactSet($stored, $moduleIds);

        $this->transactions->transactional(function () use ($version, $moduleIds): void {
            $this->modules->reorder($version->id(), $moduleIds);
        });

        $ordered = array_map(static fn (CourseModuleId $id): string => $id->value(), $moduleIds);

        $this->audit->record(
            'course.modules_reordered',
            $command->actorUserId,
            $course->organisationId()->value(),
            'course_module',
            $version->id()->value(),
            ['course_id' => $courseId->value(), 'module_ids' => implode(',', $ordered)],
        );

        return $ordered;
    }

    /**
     * @param list<CourseModule>   $stored
     * @param list<CourseModuleId> $requested
     */
    private function assertExactSet(array $stored, array $requested): void
    {
        $storedIds = array_map(static fn (CourseModule $module): string => $module->id()->value(), $stored);
        $requestedIds = array_map(static fn (CourseModuleId $id): string => $id->value(), $requested);

        $expected = $storedIds;
        sort($expected);
        $actual = $requestedIds;
        sort($actual);

        if ($expected !== $actual || \count($requestedIds) !== \count(array_unique($requestedIds))) {
            throw ValidationException::withErrors([
                'moduleIds' => ['The reorder list must contain exactly the current modules of the draft version.'],
            ]);
        }
    }
}
