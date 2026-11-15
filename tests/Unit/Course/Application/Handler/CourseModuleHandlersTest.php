<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\AddModuleCommand;
use SovereignStack\Lms\Course\Application\Command\RemoveModuleCommand;
use SovereignStack\Lms\Course\Application\Command\ReorderModulesCommand;
use SovereignStack\Lms\Course\Application\Handler\AddModuleHandler;
use SovereignStack\Lms\Course\Application\Handler\RemoveModuleHandler;
use SovereignStack\Lms\Course\Application\Handler\ReorderModulesHandler;
use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotEditableException;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Tests\Unit\Course\Application\CourseApplicationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseModuleHandlersTest extends CourseApplicationTestCase
{
    public function testAddModuleAppendsAtNextPosition(): void
    {
        [$course, $version] = $this->draftCourse();
        $this->addModule($version, 'Existing');

        $moduleId = $this->addHandler()(new AddModuleCommand(
            self::OWNER_ID,
            $course->id()->value(),
            '  New module ',
            ' Description ',
            true,
        ));

        $module = $this->modules->findById($moduleId);
        self::assertNotNull($module);
        self::assertSame('New module', $module->title());
        self::assertSame('Description', $module->description());
        self::assertSame(1, $module->position());
        self::assertTrue($module->isRequired());
        self::assertTrue($this->audit->hasEvent('course.module_added'));
    }

    public function testAddModuleRejectsPublishedVersion(): void
    {
        [$course] = $this->publishedCourse();

        $this->expectException(CourseNotEditableException::class);

        $this->addHandler()(new AddModuleCommand(self::OWNER_ID, $course->id()->value(), 'Module', '', true));
    }

    public function testRemoveModuleCompactsPositions(): void
    {
        [$course, $version] = $this->draftCourse();
        $first = $this->addModule($version, 'First');
        $second = $this->addModule($version, 'Second');
        $third = $this->addModule($version, 'Third');

        $this->removeHandler()(new RemoveModuleCommand(
            self::OWNER_ID,
            $course->id()->value(),
            $second->id()->value(),
        ));

        $remaining = $this->modules->listByVersion($version->id());
        self::assertSame([$first->id()->value(), $third->id()->value()], $this->ids($remaining));
        self::assertSame([0, 1], array_map(static fn (CourseModule $module): int => $module->position(), $remaining));
        self::assertTrue($this->audit->hasEvent('course.module_removed'));
    }

    public function testReorderModulesAppliesExactSet(): void
    {
        [$course, $version] = $this->draftCourse();
        $first = $this->addModule($version, 'First');
        $second = $this->addModule($version, 'Second');
        $third = $this->addModule($version, 'Third');

        $ordered = $this->reorderHandler()(new ReorderModulesCommand(
            self::OWNER_ID,
            $course->id()->value(),
            [$third->id()->value(), $first->id()->value(), $second->id()->value()],
        ));

        self::assertSame([$third->id()->value(), $first->id()->value(), $second->id()->value()], $ordered);
        self::assertSame($ordered, $this->ids($this->modules->listByVersion($version->id())));
        self::assertTrue($this->audit->hasEvent('course.modules_reordered'));
    }

    public function testReorderModulesRejectsIncompleteSet(): void
    {
        [$course, $version] = $this->draftCourse();
        $first = $this->addModule($version, 'First');
        $second = $this->addModule($version, 'Second');
        $this->addModule($version, 'Third');

        try {
            $this->reorderHandler()(new ReorderModulesCommand(
                self::OWNER_ID,
                $course->id()->value(),
                [$second->id()->value(), $first->id()->value()],
            ));
            self::fail('An incomplete reorder list must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('moduleIds', $exception->errors());
            self::assertSame([$first->id()->value(), $second->id()->value()], array_slice($this->ids($this->modules->listByVersion($version->id())), 0, 2));
        }
    }

    private function addHandler(): AddModuleHandler
    {
        return new AddModuleHandler(
            $this->policy,
            $this->versionResolver,
            $this->courses,
            $this->modules,
            $this->audit,
            $this->uuid,
            $this->clock,
        );
    }

    private function removeHandler(): RemoveModuleHandler
    {
        return new RemoveModuleHandler(
            $this->policy,
            $this->versionResolver,
            $this->courses,
            $this->modules,
            $this->audit,
            $this->transactions,
            $this->clock,
        );
    }

    private function reorderHandler(): ReorderModulesHandler
    {
        return new ReorderModulesHandler(
            $this->policy,
            $this->versionResolver,
            $this->courses,
            $this->modules,
            $this->audit,
            $this->transactions,
        );
    }

    /**
     * @param list<CourseModule> $modules
     *
     * @return list<string>
     */
    private function ids(array $modules): array
    {
        return array_map(static fn (CourseModule $module): string => $module->id()->value(), $modules);
    }
}
