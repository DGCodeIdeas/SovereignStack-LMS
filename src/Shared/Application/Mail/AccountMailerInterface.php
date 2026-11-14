<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Application\Mail;

use SovereignStack\Lms\Identity\Domain\ValueObject\Email;

interface AccountMailerInterface
{
    public function sendEmailVerification(Email $email, string $rawToken): void;

    public function sendPasswordReset(Email $email, string $rawToken): void;
}
