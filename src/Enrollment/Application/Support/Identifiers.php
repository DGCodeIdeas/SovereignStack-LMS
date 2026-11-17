<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Support;

use SovereignStack\Lms\Enrollment\Domain\Exception\EnrollmentNotFoundException;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\EnrollmentId;

final class Identifiers
{
    private function __construct() {}

    public static function enrollmentId(string $value): EnrollmentId
    {
        try {
            return EnrollmentId::fromString($value);
        } catch (\InvalidArgumentException) {
            throw EnrollmentNotFoundException::forId($value);
        }
    }
}
