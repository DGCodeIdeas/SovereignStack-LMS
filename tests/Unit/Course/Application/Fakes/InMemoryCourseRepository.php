<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Fakes;

use SovereignStack\Lms\Course\Domain\Entity\Course;
use SovereignStack\Lms\Course\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdInterface;

final class InMemoryCourseRepository implements CourseRepositoryInterface
{
    /**
     * @var array<string, Course>
     */
    public array $courses = [];

    public int $publishedVersionUpdates = 0;

    public int $publishedVersionClears = 0;

    public function save(Course $course): void
    {
        $this->courses[$course->id()->value()] = $course;
    }

    public function findById(CourseId $id): ?Course
    {
        return $this->courses[$id->value()] ?? null;
    }

    public function findByOrganisationAndSlug(OrganisationIdInterface $organisationId, string $slug): ?Course
    {
        foreach ($this->courses as $course) {
            if ($course->organisationId()->value() === $organisationId->value() && $course->slug() === $slug) {
                return $course;
            }
        }

        return null;
    }

    public function listByOrganisation(OrganisationIdInterface $organisationId): array
    {
        $result = [];

        foreach ($this->courses as $course) {
            if ($course->organisationId()->value() === $organisationId->value()) {
                $result[] = $course;
            }
        }

        usort($result, static fn (Course $left, Course $right): int => $left->slug() <=> $right->slug());

        return $result;
    }

    public function updatePublishedVersion(Course $course): void
    {
        ++$this->publishedVersionUpdates;
        $this->save($course);
    }

    public function clearPublishedVersion(Course $course): void
    {
        ++$this->publishedVersionClears;
        $this->save($course);
    }
}
