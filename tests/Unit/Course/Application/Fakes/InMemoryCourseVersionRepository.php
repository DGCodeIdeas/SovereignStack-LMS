<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Fakes;

use SovereignStack\Lms\Course\Domain\Entity\CourseVersion;
use SovereignStack\Lms\Course\Domain\Enum\CourseVersionStatus;
use SovereignStack\Lms\Course\Domain\Repository\CourseVersionRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;

final class InMemoryCourseVersionRepository implements CourseVersionRepositoryInterface
{
    /**
     * @var array<string, CourseVersion>
     */
    public array $versions = [];

    public function save(CourseVersion $version): void
    {
        $this->versions[$version->id()->value()] = $version;
    }

    public function findById(CourseVersionId $id): ?CourseVersion
    {
        return $this->versions[$id->value()] ?? null;
    }

    public function findDraftByCourse(CourseId $courseId): ?CourseVersion
    {
        $draft = null;

        foreach ($this->versions as $version) {
            if ($version->courseId()->value() !== $courseId->value() || CourseVersionStatus::DRAFT !== $version->status()) {
                continue;
            }

            if (null === $draft || $version->versionNumber() > $draft->versionNumber()) {
                $draft = $version;
            }
        }

        return $draft;
    }

    public function nextVersionNumber(CourseId $courseId): int
    {
        $next = 1;

        foreach ($this->versions as $version) {
            if ($version->courseId()->value() === $courseId->value() && $version->versionNumber() >= $next) {
                $next = $version->versionNumber() + 1;
            }
        }

        return $next;
    }

    public function listByCourse(CourseId $courseId): array
    {
        $result = [];

        foreach ($this->versions as $version) {
            if ($version->courseId()->value() === $courseId->value()) {
                $result[] = $version;
            }
        }

        usort($result, static fn (CourseVersion $left, CourseVersion $right): int => $left->versionNumber() <=> $right->versionNumber());

        return $result;
    }
}
