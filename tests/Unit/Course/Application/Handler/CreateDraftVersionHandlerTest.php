<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\CreateDraftVersionCommand;
use SovereignStack\Lms\Course\Application\Handler\CreateDraftVersionHandler;
use SovereignStack\Lms\Course\Domain\Enum\CourseVersionStatus;
use SovereignStack\Lms\Shared\Application\Exception\ConflictException;
use SovereignStack\Lms\Tests\Unit\Course\Application\CourseApplicationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class CreateDraftVersionHandlerTest extends CourseApplicationTestCase
{
    public function testCreatesNextDraftVersionFromPublishedVersion(): void
    {
        [$course, $published] = $this->publishedCourse();

        $versionId = $this->handler()(new CreateDraftVersionCommand(self::OWNER_ID, $course->id()->value()));

        $draft = $this->versions->findById($versionId);
        self::assertNotNull($draft);
        self::assertSame(2, $draft->versionNumber());
        self::assertSame(CourseVersionStatus::DRAFT, $draft->status());
        self::assertSame($published->title(), $draft->title());
        self::assertSame($published->description(), $draft->description());
        self::assertCount(2, $this->versions->listByCourse($course->id()));
        self::assertTrue($this->audit->hasEvent('course.draft_version_created'));
    }

    public function testRejectsWhenDraftAlreadyExists(): void
    {
        [$course] = $this->draftCourse();

        $this->expectException(ConflictException::class);

        $this->handler()(new CreateDraftVersionCommand(self::OWNER_ID, $course->id()->value()));
    }

    private function handler(): CreateDraftVersionHandler
    {
        return new CreateDraftVersionHandler(
            $this->policy,
            $this->courses,
            $this->versions,
            $this->audit,
            $this->uuid,
            $this->clock,
        );
    }
}
