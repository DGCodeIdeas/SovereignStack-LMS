<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Support\Fakes;

use SovereignStack\Lms\Identity\Domain\Entity\UserToken;
use SovereignStack\Lms\Identity\Domain\Enum\TokenPurpose;
use SovereignStack\Lms\Identity\Domain\Repository\UserTokenRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;

final class InMemoryUserTokenRepository implements UserTokenRepositoryInterface
{
    /**
     * @var array<string, UserToken>
     */
    private array $tokens = [];

    public function replaceActiveForPurpose(UserId $userId, TokenPurpose $purpose, UserToken $token): void
    {
        foreach ($this->tokens as $hash => $stored) {
            if ($stored->userId()->equals($userId) && $stored->purpose() === $purpose && !$stored->isConsumed()) {
                unset($this->tokens[$hash]);
            }
        }

        $this->tokens[$token->tokenHash()] = $token;
    }

    public function findByHash(string $tokenHash): ?UserToken
    {
        return $this->tokens[$tokenHash] ?? null;
    }

    public function markConsumed(UserToken $token): void
    {
        $this->tokens[$token->tokenHash()] = $token;
    }

    public function deleteExpired(\DateTimeImmutable $before): int
    {
        $deleted = 0;

        foreach ($this->tokens as $hash => $stored) {
            if ($stored->expiresAt() < $before && !$stored->isConsumed()) {
                unset($this->tokens[$hash]);
                ++$deleted;
            }
        }

        return $deleted;
    }

    public function count(): int
    {
        return \count($this->tokens);
    }
}
