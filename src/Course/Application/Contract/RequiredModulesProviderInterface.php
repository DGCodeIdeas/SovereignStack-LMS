<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Contract;

interface RequiredModulesProviderInterface
{
    /**
     * @return list<PublishedModule>
     */
    public function requiredModules(string $versionId): array;

    public function publishedVersionForCourse(string $courseId): ?PublishedCourseVersion;
}
