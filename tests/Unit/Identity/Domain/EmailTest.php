<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Identity\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Identity\Domain\ValueObject\Email;

/**
 * @internal
 *
 * @coversNothing
 */
final class EmailTest extends TestCase
{
    public function testTrimsAndLowercases(): void
    {
        $email = Email::fromString('  Learner.Example@Example.COM ');

        self::assertSame('learner.example@example.com', $email->value());
        self::assertSame('learner.example@example.com', (string) $email);
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Email::fromString('not-an-email');
    }

    public function testRejectsEmptyEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Email::fromString('   ');
    }

    public function testRejectsEmailLongerThan255Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Email::fromString(str_repeat('a', 250).'@example.com');
    }

    public function testEqualsComparesNormalizedValues(): void
    {
        $email = Email::fromString('Learner@Example.com');

        self::assertTrue($email->equals(Email::fromString('learner@example.com')));
        self::assertFalse($email->equals(Email::fromString('other@example.com')));
    }
}
