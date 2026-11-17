<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Security;

use SovereignStack\Lms\Enrollment\Application\Contract\PermissionCheckerInterface;
use SovereignStack\Lms\Enrollment\Domain\Entity\Enrollment;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;

/**
 * Central authorisation rules for enrollment operations.
 *
 * Learners may act on their own enrollment. Managers and administrators are
 * recognised through identity permissions scoped to the enrollment's
 * organisation, which keeps the policy independent from any other module's
 * domain model.
 */
final readonly class EnrollmentAccessPolicy
{
    private const VIEW_ANY_PERMISSION = 'enrollment.view.any';
    private const CREATE_PERMISSION = 'enrollment.create';
    private const USER_MANAGE_PERMISSION = 'user.manage';

    public function __construct(
        private PermissionCheckerInterface $permissions,
    ) {}

    public function requireSelfOrManager(string $actor, Enrollment $enrollment): void
    {
        if ($this->isLearner($actor, $enrollment)) {
            return;
        }

        if ($this->permissions->can($actor, self::VIEW_ANY_PERMISSION, $enrollment->organisationId()->value())) {
            return;
        }

        throw new ForbiddenException('You are not allowed to view this enrollment.');
    }

    public function requireSelfOrAdmin(string $actor, Enrollment $enrollment): void
    {
        if ($this->isLearner($actor, $enrollment)) {
            return;
        }

        if ($this->permissions->can($actor, self::USER_MANAGE_PERMISSION, $enrollment->organisationId()->value())) {
            return;
        }

        throw new ForbiddenException('You are not allowed to manage this enrollment.');
    }

    public function requireCreate(string $actor, string $learnerId, string $organisationId): void
    {
        if ($this->isSelf($actor, $learnerId)) {
            $this->permissions->require($actor, self::CREATE_PERMISSION, $organisationId);

            return;
        }

        $this->permissions->require($actor, self::USER_MANAGE_PERMISSION, $organisationId);
    }

    public function isAdmin(string $actor, string $organisationId): bool
    {
        return $this->permissions->can($actor, self::USER_MANAGE_PERMISSION, $organisationId);
    }

    public function isManager(string $actor, string $organisationId): bool
    {
        return $this->permissions->can($actor, self::VIEW_ANY_PERMISSION, $organisationId);
    }

    private function isLearner(string $actor, Enrollment $enrollment): bool
    {
        return $this->isSelf($actor, $enrollment->learnerUserId()->value());
    }

    private function isSelf(string $actor, string $learnerId): bool
    {
        return strtolower(trim($actor)) === strtolower(trim($learnerId));
    }
}
