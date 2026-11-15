<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Identity\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Identity\Application\Security\PermissionChecker;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Tests\Support\Fakes\InMemoryRoleRepository;

/**
 * @internal
 *
 * @coversNothing
 */
final class PermissionCheckerTest extends TestCase
{
    private const USER = '01900000-0000-7000-8000-000000000001';
    private const ORGANISATION = '01900000-0000-7000-8000-000000000100';

    private InMemoryRoleRepository $roles;
    private PermissionChecker $checker;

    protected function setUp(): void
    {
        $this->roles = new InMemoryRoleRepository();
        $this->checker = new PermissionChecker($this->roles);
    }

    public function testGlobalPermissionIsGranted(): void
    {
        $this->roles->grantGlobal(self::USER, 'user.manage');

        self::assertTrue($this->checker->can(self::USER, 'user.manage'));
    }

    public function testMissingGlobalPermissionIsDenied(): void
    {
        self::assertFalse($this->checker->can(self::USER, 'user.manage'));
    }

    public function testOrganisationScopedPermissionIsGrantedForThatOrganisation(): void
    {
        $this->roles->grantScoped(self::USER, self::ORGANISATION, 'organisation.members.view');

        self::assertTrue($this->checker->can(self::USER, 'organisation.members.view', self::ORGANISATION));
    }

    public function testOrganisationScopedPermissionIsNotGrantedGlobally(): void
    {
        $this->roles->grantScoped(self::USER, self::ORGANISATION, 'organisation.members.view');

        self::assertFalse($this->checker->can(self::USER, 'organisation.members.view'));
    }

    public function testRequireThrowsWhenPermissionIsMissing(): void
    {
        $this->expectException(ForbiddenException::class);

        $this->checker->require(self::USER, 'user.manage');
    }

    public function testRequireDoesNotThrowWhenPermissionIsGranted(): void
    {
        $this->roles->grantGlobal(self::USER, 'user.manage');

        $this->checker->require(self::USER, 'user.manage');

        self::assertTrue($this->checker->can(self::USER, 'user.manage'));
    }
}
