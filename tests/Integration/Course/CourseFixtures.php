<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Course;

use SovereignStack\Lms\Tests\Integration\IdentityOrganisationFixtures;
use Symfony\Component\Uid\Uuid;

/**
 * Helpers for the Phase 2C course content integration tests. Fixture rows are
 * inserted directly so schema-level behaviour can be exercised without going
 * through the repositories under test.
 */
trait CourseFixtures
{
    use IdentityOrganisationFixtures;

    protected function timestamp(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }

    protected function insertCourseRow(
        string $organisationId,
        string $ownerUserId,
        string $slug,
        string $visibility = 'organisation_only',
        ?string $currentPublishedVersionId = null,
        ?string $currentPublishedVersionCourseId = null,
    ): string {
        $id = Uuid::v7()->toRfc4122();
        $now = $this->timestamp();

        $this->connection()->insert('courses', [
            'id' => $id,
            'organisation_id' => $organisationId,
            'owner_user_id' => $ownerUserId,
            'slug' => $slug,
            'visibility' => $visibility,
            'current_published_version_id' => $currentPublishedVersionId,
            'current_published_version_course_id' => $currentPublishedVersionCourseId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    protected function insertVersionRow(
        string $courseId,
        string $createdBy,
        int $versionNumber = 1,
        string $status = 'draft',
        ?string $publishedAt = null,
    ): string {
        $id = Uuid::v7()->toRfc4122();
        $now = $this->timestamp();

        $this->connection()->insert('course_versions', [
            'id' => $id,
            'course_id' => $courseId,
            'version_number' => $versionNumber,
            'title' => 'Version '.$versionNumber,
            'description' => 'Description '.$versionNumber,
            'status' => $status,
            'published_at' => $publishedAt,
            'created_by' => $createdBy,
            'created_at' => $now,
        ]);

        return $id;
    }

    protected function insertModuleRow(
        string $courseVersionId,
        string $title,
        int $position,
        bool $isRequired = true,
    ): string {
        $id = Uuid::v7()->toRfc4122();
        $now = $this->timestamp();

        $this->connection()->insert('course_modules', [
            'id' => $id,
            'course_version_id' => $courseVersionId,
            'title' => $title,
            'description' => null,
            'position' => $position,
            'is_required' => $isRequired ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    protected function insertLessonRow(string $courseModuleId, string $title, int $position): string
    {
        $id = Uuid::v7()->toRfc4122();
        $now = $this->timestamp();

        $this->connection()->insert('lessons', [
            'id' => $id,
            'course_module_id' => $courseModuleId,
            'title' => $title,
            'position' => $position,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    protected function insertContentRow(string $lessonId, string $html = '<p>Content</p>'): string
    {
        $id = Uuid::v7()->toRfc4122();
        $now = $this->timestamp();

        $this->connection()->insert('lesson_contents', [
            'id' => $id,
            'lesson_id' => $lessonId,
            'content_html' => $html,
            'content_metadata' => '{"format":"html"}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    protected function insertCategoryRow(string $organisationId, string $name, string $slug): string
    {
        $id = Uuid::v7()->toRfc4122();

        $this->connection()->insert('course_categories', [
            'id' => $id,
            'organisation_id' => $organisationId,
            'name' => $name,
            'slug' => $slug,
            'created_at' => $this->timestamp(),
        ]);

        return $id;
    }

    protected function truncateCourseTables(): void
    {
        $this->truncate(
            'course_instructors',
            'course_category_assignments',
            'course_categories',
            'lesson_contents',
            'lessons',
            'course_modules',
            'courses',
            'course_versions',
        );
    }
}
