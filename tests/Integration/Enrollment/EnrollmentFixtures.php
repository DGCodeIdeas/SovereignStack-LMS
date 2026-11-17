<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Enrollment;

use Doctrine\DBAL\Connection;
use SovereignStack\Lms\Tests\Integration\Course\CourseFixtures;
use Symfony\Component\Uid\Uuid;

trait EnrollmentFixtures
{
    use CourseFixtures;

    abstract protected function connection(): Connection;

    protected function truncateEnrollmentTables(): void
    {
        $this->truncate(
            'course_completions',
            'lesson_progress',
            'module_progress',
            'enrollments',
            'lesson_contents',
            'lessons',
            'course_modules',
            'course_versions',
            'courses',
            'user_tokens',
            'organisation_memberships',
            'user_roles',
            'user_profiles',
            'users',
            'organisations',
        );
    }

    protected function insertEnrollmentRow(
        string $organisationId,
        string $learnerUserId,
        string $courseId,
        string $courseVersionId,
        string $status = 'active',
        ?string $enrolledAt = null,
        ?string $completedAt = null,
        ?string $cancelledAt = null,
    ): string {
        $id = Uuid::v7()->toRfc4122();
        $now = $this->timestamp();
        $enrolledAt ??= $now;

        $this->connection()->insert('enrollments', [
            'id' => $id,
            'organisation_id' => $organisationId,
            'learner_user_id' => $learnerUserId,
            'course_id' => $courseId,
            'course_version_id' => $courseVersionId,
            'status' => $status,
            'enrolled_at' => $enrolledAt,
            'completed_at' => $completedAt,
            'cancelled_at' => $cancelledAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    /**
     * @return array{organisationId: string, learnerUserId: string, ownerUserId: string, courseId: string, courseVersionId: string}
     */
    protected function seedEnrollmentContext(string $prefix): array
    {
        $organisationId = $this->insertOrganisation($prefix.'-'.bin2hex(random_bytes(4)));
        $learnerUserId = $this->insertUser($prefix.'-learner-'.bin2hex(random_bytes(4)).'@example.com');
        $ownerUserId = $this->insertUser($prefix.'-owner-'.bin2hex(random_bytes(4)).'@example.com');
        $courseId = $this->insertCourseRow($organisationId, $ownerUserId, $prefix.'-'.bin2hex(random_bytes(4)));
        $courseVersionId = $this->insertVersionRow($courseId, $ownerUserId, 1, 'published', $this->timestamp());

        return [
            'organisationId' => $organisationId,
            'learnerUserId' => $learnerUserId,
            'ownerUserId' => $ownerUserId,
            'courseId' => $courseId,
            'courseVersionId' => $courseVersionId,
        ];
    }

    /**
     * @return array{moduleId: string, lessonId: string}
     */
    protected function seedModuleAndLesson(string $courseVersionId, string $prefix): array
    {
        $moduleId = $this->insertModuleRow($courseVersionId, $prefix.' Module', 0, true);
        $lessonId = $this->insertLessonRow($moduleId, $prefix.' Lesson', 0);

        return ['moduleId' => $moduleId, 'lessonId' => $lessonId];
    }
}
