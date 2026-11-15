<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Infrastructure\Security;

use SovereignStack\Lms\Identity\Application\Contract\PasswordHasherInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final readonly class SymfonyPasswordHasher implements PasswordHasherInterface
{
    public function __construct(
        private PasswordHasherFactoryInterface $factory,
    ) {}

    public function hash(string $plain): string
    {
        return $this->factory->getPasswordHasher(PasswordUser::class)->hash($plain);
    }

    public function verify(string $hash, string $plain): bool
    {
        return $this->factory->getPasswordHasher(PasswordUser::class)->verify($hash, $plain);
    }
}
