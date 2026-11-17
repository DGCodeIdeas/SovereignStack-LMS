<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Contract;

/**
 * Presentation-safe view of the published course version a learner is bound
 * to. It exposes only identifiers and titles, never authoring metadata or
 * lesson bodies.
 */
final readonly class EnrollmentCourseView
{
    /**
     * @var list<array{
     *     id: string,
     *     title: string,
     *     isRequired: bool,
     *     lessons: list<array{id: string, title: string}>
     * }>
     */
    public array $modules;

    /**
     * @param list<array{
     *     id: string,
     *     title: string,
     *     isRequired: bool,
     *     lessons: list<array{id: string, title: string}>
     * }> $modules
     */
    public function __construct(
        public string $id,
        public string $versionId,
        public string $title,
        array $modules,
    ) {
        $this->modules = $modules;
    }
}
