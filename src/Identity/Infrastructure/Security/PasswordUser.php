<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Infrastructure\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class PasswordUser implements PasswordAuthenticatedUserInterface
{
    public function getPassword(): string
    {
        return '';
    }
}
