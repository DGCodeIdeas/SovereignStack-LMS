<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Enrollment\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Enrollment\Application\Security\EnrollmentAccessPolicy;
use SovereignStack\Lms\Enrollment\Domain\Entity\Enrollment;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\EnrollmentId;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Tests\Support\Fakes\FakePermissionChecker;

/**
 * @internal
 *
 * @coversNothing
 */
final class EnrollmentAccessPolicyTest extends TestCase
{
    private const ORGANISATION_ID = '01900000-0000-7000-8000-000000000001';
    private const LEARNER_ID = '01900000-0000-7000-8000-000000000002';
    private const OTHER_ID = '01900000-0000-7000-8000-000000000003';
    private const COURSE_ID = '01900000-0000-7000-8000-000000000010';
    private const VERSION_ID = '01900000-0000-7000-8000-000000000011';

    public function testRequireSelfOrManagerAllowsLearner(): void
    {
        $policy = new EnrollmentAccessPolicy(new FakePermissionChecker(['enrollment.view.any']));

        $policy->requireSelfOrManager(self::LEARNER_ID, $this->enrollment());
        $this->addToAssertionCount(1);
    }

    public function testRequireSelfOrManagerAllowsManager(): void
    {
        $policy = new EnrollmentAccessPolicy(new FakePermissionChecker());

        $policy->requireSelfOrManager(self::OTHER_ID, $this->enrollment());
        $this->addToAssertionCount(1);
    }

    public function testRequireSelfOrManagerDeniesUnrelatedActor(): void
    {
        $policy = new EnrollmentAccessPolicy(new FakePermissionChecker(['enrollment.view.any']));

        $this->expectException(ForbiddenException::class);

        $policy->requireSelfOrManager(self::OTHER_ID, $this->enrollment());
    }

    public function testRequireSelfOrAdminAllowsLearner(): void
    {
        $policy = new EnrollmentAccessPolicy(new FakePermissionChecker(['user.manage']));

        $policy->requireSelfOrAdmin(self::LEARNER_ID, $this->enrollment());
        $this->addToAssertionCount(1);
    }

    public function testRequireSelfOrAdminAllowsAdmin(): void
    {
        $policy = new EnrollmentAccessPolicy(new FakePermissionChecker());

        $policy->requireSelfOrAdmin(self::OTHER_ID, $this->enrollment());
        $this->addToAssertionCount(1);
    }

    public function testRequireSelfOrAdminDeniesUnrelatedActor(): void
    {
        $policy = new EnrollmentAccessPolicy(new FakePermissionChecker(['user.manage']));

        $this->expectException(ForbiddenException::class);

        $policy->requireSelfOrAdmin(self::OTHER_ID, $this->enrollment());
    }

    public function testRequireCreateSelfRequiresEnrollmentCreate(): void
    {
        $policy = new EnrollmentAccessPolicy(new FakePermissionChecker(['enrollment.create']));

        $this->expectException(ForbiddenException::class);

        $policy->requireCreate(self::LEARNER_ID, self::LEARNER_ID, self::ORGANISATION_ID);
    }

    public function testRequireCreateSelfPassesWithEnrollmentCreate(): void
    {
        $policy = new EnrollmentAccessPolicy(new FakePermissionChecker());

        $policy->requireCreate(self::LEARNER_ID, self::LEARNER_ID, self::ORGANISATION_ID);
        $this->addToAssertionCount(1);
    }

    public function testRequireCreateOtherRequiresUserManage(): void
    {
        $policy = new EnrollmentAccessPolicy(new FakePermissionChecker(['user.manage']));

        $this->expectException(ForbiddenException::class);

        $policy->requireCreate(self::OTHER_ID, self::LEARNER_ID, self::ORGANISATION_ID);
    }

    private function enrollment(): Enrollment
    {
        return Enrollment::create(
            EnrollmentId::fromString('01900000-0000-7000-8000-000000000030'),
            OrganisationIdReference::fromString(self::ORGANISATION_ID),
            UserIdReference::fromString(self::LEARNER_ID),
            self::COURSE_ID,
            self::VERSION_ID,
            new \DateTimeImmutable('2026-11-16T10:00:00+00:00'),
        );
    }
}
