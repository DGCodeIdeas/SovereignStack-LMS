<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\ValueObject;

final readonly class PermissionCode
{
    public const COURSE_CREATE = 'course.create';
    public const COURSE_UPDATE = 'course.update';
    public const COURSE_PUBLISH = 'course.publish';
    public const COURSE_ARCHIVE = 'course.archive';
    public const COURSE_VIEW = 'course.view';
    public const ENROLLMENT_CREATE = 'enrollment.create';
    public const ENROLLMENT_CANCEL_SELF = 'enrollment.cancel.self';
    public const ENROLLMENT_CANCEL_OTHER = 'enrollment.cancel.other';
    public const ENROLLMENT_VIEW_SELF = 'enrollment.view.self';
    public const ENROLLMENT_VIEW_ANY = 'enrollment.view.any';
    public const PROGRESS_RECORD_SELF = 'progress.record.self';
    public const PROGRESS_VIEW_SELF = 'progress.view.self';
    public const PROGRESS_VIEW_ANY = 'progress.view.any';
    public const ORGANISATION_MEMBERS_VIEW = 'organisation.members.view';
    public const ORGANISATION_MEMBERS_MANAGE = 'organisation.members.manage';
    public const USER_ROLES_ASSIGN = 'user.roles.assign';
    public const USER_MANAGE = 'user.manage';
    public const AUDIT_VIEW = 'audit.view';
    public const FILE_UPLOAD = 'file.upload';
    public const FILE_DOWNLOAD = 'file.download';

    private const PATTERN = '/^[a-z0-9][a-z0-9:._-]{0,126}$/';

    private function __construct(
        private string $value,
    ) {}

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(sprintf('Invalid permission code "%s".', $value));
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
