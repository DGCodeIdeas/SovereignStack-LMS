<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Domain\Entity;

use SovereignStack\Lms\Organisation\Domain\Enum\MembershipStatus;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdInterface;

final class Membership
{
    private function __construct(
        private string $id,
        private OrganisationId $organisationId,
        private string $userId,
        private MembershipStatus $status,
        private ?\DateTimeImmutable $joinedAt,
        private ?\DateTimeImmutable $endedAt,
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function join(
        string $id,
        OrganisationId $organisationId,
        UserIdInterface $userId,
        \DateTimeImmutable $now,
    ): self {
        return new self($id, $organisationId, $userId->value(), MembershipStatus::ACTIVE, $now, null, $now);
    }

    public static function restore(
        string $id,
        OrganisationId $organisationId,
        string $userId,
        MembershipStatus $status,
        ?\DateTimeImmutable $joinedAt,
        ?\DateTimeImmutable $endedAt,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $organisationId, $userId, $status, $joinedAt, $endedAt, $createdAt);
    }

    public function end(\DateTimeImmutable $now): void
    {
        if (MembershipStatus::ENDED === $this->status) {
            return;
        }

        $this->status = MembershipStatus::ENDED;
        $this->endedAt = $now;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function organisationId(): OrganisationId
    {
        return $this->organisationId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function status(): MembershipStatus
    {
        return $this->status;
    }

    public function joinedAt(): ?\DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function endedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
