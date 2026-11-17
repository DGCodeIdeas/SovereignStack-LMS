<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Learning\Application\Contract;

interface RequiredModulesInterface
{
    /**
     * @return list<string>
     */
    public function requiredModuleIds(string $courseVersionId): array;
}
