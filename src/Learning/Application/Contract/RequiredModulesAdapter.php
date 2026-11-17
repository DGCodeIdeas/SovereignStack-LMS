<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Application\Contract;

use SovereignStack\Lms\Course\Application\Contract\CourseContentProviderInterface;

/**
 * Bridges the published course content contract to the learning-owned contract
 * so learning services never import the course module directly.
 */
final readonly class RequiredModulesAdapter implements RequiredModulesInterface
{
    public function __construct(
        private CourseContentProviderInterface $content,
    ) {}

    public function requiredModuleIds(string $courseVersionId): array
    {
        return array_map(
            static fn ($module): string => $module->id,
            $this->content->requiredModules($courseVersionId),
        );
    }
}
