<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Security;

use SovereignStack\Lms\Identity\Domain\Entity\UserToken;
use SovereignStack\Lms\Identity\Domain\Enum\TokenPurpose;
use SovereignStack\Lms\Identity\Domain\Exception\TokenInvalidException;
use SovereignStack\Lms\Identity\Domain\Repository\UserTokenRepositoryInterface;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Shared\Application\Token\TokenGeneratorInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;

final readonly class UserTokenService
{
    private const EMAIL_VERIFICATION_TTL = '+24 hours';
    private const PASSWORD_RESET_TTL = '+1 hour';

    public function __construct(
        private UserTokenRepositoryInterface $tokens,
        private TokenGeneratorInterface $generator,
        private UuidGeneratorInterface $uuid,
        private ClockInterface $clock,
    ) {}

    public function issue(UserId $userId, TokenPurpose $purpose, ?\DateTimeImmutable $now = null): string
    {
        $now ??= $this->clock->now();
        $rawToken = $this->generator->generate();
        $tokenHash = hash('sha256', $rawToken);

        $token = UserToken::issue(
            $this->uuid->generate(),
            $userId,
            $purpose,
            $tokenHash,
            $now->modify(self::ttlFor($purpose)),
            $now,
        );

        $this->tokens->replaceActiveForPurpose($userId, $purpose, $token);

        return $rawToken;
    }

    public function consume(string $rawToken, TokenPurpose $expectedPurpose, ?\DateTimeImmutable $now = null): UserId
    {
        $now ??= $this->clock->now();
        $token = $this->tokens->findByHash(hash('sha256', $rawToken));

        if (null === $token || $token->purpose() !== $expectedPurpose) {
            throw TokenInvalidException::unknown();
        }

        $token->consume($now);
        $this->tokens->markConsumed($token);

        return $token->userId();
    }

    private static function ttlFor(TokenPurpose $purpose): string
    {
        return match ($purpose) {
            TokenPurpose::EMAIL_VERIFICATION => self::EMAIL_VERIFICATION_TTL,
            TokenPurpose::PASSWORD_RESET => self::PASSWORD_RESET_TTL,
        };
    }
}
