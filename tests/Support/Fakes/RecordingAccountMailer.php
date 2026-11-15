<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Support\Fakes;

use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Shared\Application\Mail\AccountMailerInterface;

final class RecordingAccountMailer implements AccountMailerInterface
{
    /**
     * @var list<array{email: string, token: string}>
     */
    public array $verifications = [];

    /**
     * @var list<array{email: string, token: string}>
     */
    public array $resets = [];

    public function sendEmailVerification(Email $email, string $rawToken): void
    {
        $this->verifications[] = ['email' => $email->value(), 'token' => $rawToken];
    }

    public function sendPasswordReset(Email $email, string $rawToken): void
    {
        $this->resets[] = ['email' => $email->value(), 'token' => $rawToken];
    }
}
