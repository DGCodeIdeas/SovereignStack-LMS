<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Identity\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Identity\Domain\ValueObject\PermissionCode;

/**
 * @internal
 *
 * @coversNothing
 */
final class PermissionCodeTest extends TestCase
{
    public function testAllDefinedCodesAreValid(): void
    {
        $codes = [
            PermissionCode::COURSE_CREATE,
            PermissionCode::COURSE_UPDATE,
            PermissionCode::COURSE_PUBLISH,
            PermissionCode::COURSE_ARCHIVE,
            PermissionCode::COURSE_VIEW,
            PermissionCode::ENROLLMENT_CREATE,
            PermissionCode::ENROLLMENT_CANCEL_SELF,
            PermissionCode::ENROLLMENT_CANCEL_OTHER,
            PermissionCode::ENROLLMENT_VIEW_SELF,
            PermissionCode::ENROLLMENT_VIEW_ANY,
            PermissionCode::PROGRESS_RECORD_SELF,
            PermissionCode::PROGRESS_VIEW_SELF,
            PermissionCode::PROGRESS_VIEW_ANY,
            PermissionCode::ORGANISATION_MEMBERS_VIEW,
            PermissionCode::ORGANISATION_MEMBERS_MANAGE,
            PermissionCode::USER_ROLES_ASSIGN,
            PermissionCode::USER_MANAGE,
            PermissionCode::AUDIT_VIEW,
            PermissionCode::FILE_UPLOAD,
            PermissionCode::FILE_DOWNLOAD,
        ];

        foreach ($codes as $code) {
            self::assertSame($code, PermissionCode::fromString($code)->value());
        }

        self::assertCount(20, array_unique($codes));
    }

    public function testCourseViewPermissionCodeConstant(): void
    {
        self::assertSame('course.view', PermissionCode::COURSE_VIEW);
    }

    public function testAcceptsDottedCodes(): void
    {
        self::assertSame('enrollment.cancel.self', PermissionCode::fromString('enrollment.cancel.self')->value());
    }

    public function testRejectsInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PermissionCode::fromString('Course Create');
    }

    public function testRejectsTooLongValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PermissionCode::fromString(str_repeat('a', 128));
    }

    public function testEqualsComparesValues(): void
    {
        $code = PermissionCode::fromString(PermissionCode::FILE_DOWNLOAD);

        self::assertTrue($code->equals(PermissionCode::fromString(PermissionCode::FILE_DOWNLOAD)));
        self::assertFalse($code->equals(PermissionCode::fromString(PermissionCode::FILE_UPLOAD)));
    }
}
