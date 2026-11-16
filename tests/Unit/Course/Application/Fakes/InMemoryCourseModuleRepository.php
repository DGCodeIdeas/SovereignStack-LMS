<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Fakes;

use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\Repository\CourseModuleRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;

final class InMemoryCourseModuleRepository implements CourseModuleRepositoryInterface
{
    /**
     * @var array<string, CourseModule>
     */
    public array $modules = [];

    public function save(CourseModule $module): void
    {
        $this->modules[$module->id()->value()] = $module;
    }

    public function findById(CourseModuleId $id): ?CourseModule
    {
        return $this->modules[$id->value()] ?? null;
    }

    public function listByVersion(CourseVersionId $courseVersionId): array
    {
        $result = [];

        foreach ($this->modules as $module) {
            if ($module->courseVersionId()->equals($courseVersionId)) {
                $result[] = $module;
            }
        }

        usort($result, static fn (CourseModule $left, CourseModule $right): int => $left->position() <=> $right->position());

        return $result;
    }

    public function delete(CourseModuleId $id): void
    {
        unset($this->modules[$id->value()]);
    }

    public function nextPosition(CourseVersionId $courseVersionId): int
    {
        $position = 0;

        foreach ($this->modules as $module) {
            if ($module->courseVersionId()->equals($courseVersionId)) {
                $position = max($position, $module->position() + 1);
            }
        }

        return $position;
    }

    public function reorder(CourseVersionId $courseVersionId, array $moduleIds): void
    {
        foreach ($moduleIds as $position => $moduleId) {
            $module = $this->modules[$moduleId->value()] ?? throw new \InvalidArgumentException('Unknown module in reorder list.');

            if (!$module->courseVersionId()->equals($courseVersionId)) {
                throw new \InvalidArgumentException('Module does not belong to the reordered version.');
            }

            $module->changePosition($position, new \DateTimeImmutable('2026-11-15T00:00:00+00:00'));
        }
    }
}
