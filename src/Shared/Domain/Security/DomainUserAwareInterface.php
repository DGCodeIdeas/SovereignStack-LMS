<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Domain\Security;

/**
 * Implemented by the identity security user so presentation layers in other
 * modules can read the authenticated domain user id without importing the
 * identity module directly.
 */
interface DomainUserAwareInterface
{
    public function domainUserId(): string;
}
