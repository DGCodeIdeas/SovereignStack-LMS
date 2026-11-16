<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Contract;

use SovereignStack\Lms\Identity\Application\Contract\PermissionCheckerInterface as IdentityPermissionCheckerInterface;

/**
 * Course-owned view of the identity permission checker so course handlers never
 * depend on the identity module directly.
 */
interface PermissionCheckerInterface extends IdentityPermissionCheckerInterface {}
