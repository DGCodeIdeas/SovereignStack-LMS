<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Handler;

use SovereignStack\Lms\Course\Application\Command\SaveLessonContentCommand;
use SovereignStack\Lms\Course\Application\Contract\HtmlSanitizerInterface;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Application\Support\CourseVersionResolver;
use SovereignStack\Lms\Course\Application\Support\Identifiers;
use SovereignStack\Lms\Course\Domain\Entity\Lesson;
use SovereignStack\Lms\Course\Domain\Entity\LessonContent;
use SovereignStack\Lms\Course\Domain\Exception\CourseNotFoundException;
use SovereignStack\Lms\Course\Domain\Exception\LessonNotFoundException;
use SovereignStack\Lms\Course\Domain\Repository\CourseModuleRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\LessonContentRepositoryInterface;
use SovereignStack\Lms\Course\Domain\Repository\LessonRepositoryInterface;
use SovereignStack\Lms\Shared\Application\Audit\AuditLoggerInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;

final readonly class SaveLessonContentHandler
{
    public function __construct(
        private CourseAccessPolicy $policy,
        private CourseVersionResolver $versionResolver,
        private CourseRepositoryInterface $courses,
        private CourseModuleRepositoryInterface $modules,
        private LessonRepositoryInterface $lessons,
        private LessonContentRepositoryInterface $contents,
        private HtmlSanitizerInterface $sanitizer,
        private AuditLoggerInterface $audit,
        private UuidGeneratorInterface $uuid,
        private ClockInterface $clock,
    ) {}

    public function __invoke(SaveLessonContentCommand $command): void
    {
        $courseId = Identifiers::courseId($command->courseId);
        $course = $this->courses->findById($courseId) ?? throw CourseNotFoundException::forId($command->courseId);

        $this->policy->requireEdit($command->actorUserId, $course);

        $version = $this->versionResolver->requireDraft($courseId);

        $lessonId = Identifiers::lessonId($command->lessonId);
        $lesson = $this->lessons->findById($lessonId) ?? throw LessonNotFoundException::forId($command->lessonId);

        if (!$this->lessonBelongsToVersion($lesson, $version->id()->value())) {
            throw LessonNotFoundException::forId($command->lessonId);
        }

        $html = $this->sanitizer->sanitize($command->contentHtml);
        $now = $this->clock->now();
        $existing = $this->contents->findByLesson($lessonId);

        if (null === $existing) {
            $content = LessonContent::create($this->uuid->generate(), $lessonId, $html, $command->metadata, $now);
        } else {
            $content = $existing;
            $content->replaceContent($html, $command->metadata, $now);
        }

        $this->contents->save($content);

        $this->audit->record(
            'course.lesson_content_saved',
            $command->actorUserId,
            $course->organisationId()->value(),
            'lesson',
            $lessonId->value(),
            ['course_id' => $courseId->value(), 'content_id' => $content->id(), 'length' => \strlen($html)],
        );
    }

    private function lessonBelongsToVersion(Lesson $lesson, string $versionId): bool
    {
        $module = $this->modules->findById($lesson->courseModuleId());

        return null !== $module && $module->courseVersionId()->value() === $versionId;
    }
}
