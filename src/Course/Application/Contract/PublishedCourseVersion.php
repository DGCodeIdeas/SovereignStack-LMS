<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Contract;

final readonly class PublishedCourseVersion
{
    /**
     * @param list<PublishedModule> $modules
     */
    public function __construct(
        public string $courseId,
        public string $versionId,
        public string $organisationId,
        public string $title,
        public array $modules,
        public string $visibility = 'organisation_only',
        public ?string $description = null,
    ) {}
}
