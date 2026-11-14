<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Mail;

use SovereignStack\Lms\Identity\Domain\ValueObject\Email;
use SovereignStack\Lms\Shared\Application\Mail\AccountMailerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email as SymfonyEmail;

final readonly class SymfonyAccountMailer implements AccountMailerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private string $baseUrl,
        private string $mailFrom,
    ) {}

    public function sendEmailVerification(Email $email, string $rawToken): void
    {
        $link = $this->link('/verify/email/'.$rawToken);

        $this->send(
            $email,
            'Verify your email address',
            "Welcome. Confirm your email address by opening the link below:\n\n"
            .$link."\n\nIf you did not create an account you can ignore this message.",
        );
    }

    public function sendPasswordReset(Email $email, string $rawToken): void
    {
        $link = $this->link('/reset-password/'.$rawToken);

        $this->send(
            $email,
            'Reset your password',
            "A password reset was requested for your account. Open the link below to choose a new password:\n\n"
            .$link."\n\nIf you did not request this you can ignore this message.",
        );
    }

    private function send(Email $to, string $subject, string $body): void
    {
        $message = (new SymfonyEmail())
            ->from($this->mailFrom)
            ->to($to->value())
            ->subject($subject)
            ->text($body)
        ;

        $this->mailer->send($message);
    }

    private function link(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }
}
