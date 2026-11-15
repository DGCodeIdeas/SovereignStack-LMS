<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Policy;

use SovereignStack\Lms\Shared\Application\Exception\ValidationException;

final class PasswordPolicy
{
    public const MIN_LENGTH = 12;
    public const MAX_LENGTH = 128;

    public static function assertValid(string $password, string $email): void
    {
        $errors = [];
        $length = mb_strlen($password);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            $errors[] = sprintf(
                'The password must be between %d and %d characters.',
                self::MIN_LENGTH,
                self::MAX_LENGTH,
            );
        }

        if ('' !== $email && 0 === strcasecmp(trim($password), trim($email))) {
            $errors[] = 'The password must not be the same as the email address.';
        }

        if ([] !== $errors) {
            throw ValidationException::withErrors(['password' => $errors]);
        }
    }
}
