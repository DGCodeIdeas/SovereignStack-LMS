<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Infrastructure\Security;

use SovereignStack\Lms\Identity\Domain\Entity\User;
use SovereignStack\Lms\Identity\Domain\ValueObject\PermissionCode;
use SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier;
use SovereignStack\Lms\Shared\Domain\Security\DomainUserAwareInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class SecurityUser implements DomainUserAwareInterface, PasswordAuthenticatedUserInterface, UserInterface
{
    private ?string $passwordHash;

    /**
     * @param list<RoleIdentifier> $roles
     * @param list<PermissionCode> $permissions
     */
    public function __construct(
        private readonly User $user,
        private readonly array $roles,
        private readonly array $permissions,
    ) {
        $this->passwordHash = $user->passwordHash();
    }

    public function getUserIdentifier(): string
    {
        $identifier = $this->user->email()->value();

        if ('' === $identifier) {
            throw new \LogicException('An authenticated user must have an email address.');
        }

        return $identifier;
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        foreach ($this->roles as $role) {
            $roles[] = 'ROLE_'.strtoupper(str_replace([':', '-'], '_', $role->value()));
        }

        return array_values(array_unique($roles));
    }

    public function eraseCredentials(): void
    {
        $this->passwordHash = null;
    }

    public function getDomainUser(): User
    {
        return $this->user;
    }

    public function domainUserId(): string
    {
        return $this->user->id()->value();
    }

    /**
     * @return list<string>
     */
    public function getPermissions(): array
    {
        return array_map(
            static fn (PermissionCode $permission): string => $permission->value(),
            $this->permissions,
        );
    }
}
