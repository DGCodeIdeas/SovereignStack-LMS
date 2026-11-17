<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Learning\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Learning\Domain\ValueObject\LessonProgressId;
use SovereignStack\Lms\Learning\Domain\ValueObject\ModuleProgressId;

/**
 * @internal
 *
 * @coversNothing
 */
final class LearningIdentifierTest extends TestCase
{
    public function testModuleProgressIdNormalizesAndCompares(): void
    {
        $id = ModuleProgressId::fromString(' 01900000-0000-7000-8000-000000004401 ');

        self::assertSame('01900000-0000-7000-8000-000000004401', $id->value());
        self::assertSame('01900000-0000-7000-8000-000000004401', (string) $id);
        self::assertTrue($id->equals(ModuleProgressId::fromString('01900000-0000-7000-8000-000000004401')));
        self::assertFalse($id->equals(ModuleProgressId::fromString('01900000-0000-7000-8000-000000004402')));
    }

    public function testModuleProgressIdRejectsNonUuidV7(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ModuleProgressId::fromString('01900000-0000-7000-8000-0000000044');
    }

    public function testLessonProgressIdNormalizesAndCompares(): void
    {
        $id = LessonProgressId::fromString('01900000-0000-7000-8000-000000004411');

        self::assertSame('01900000-0000-7000-8000-000000004411', $id->value());
        self::assertSame('01900000-0000-7000-8000-000000004411', (string) $id);
        self::assertTrue($id->equals(LessonProgressId::fromString('01900000-0000-7000-8000-000000004411')));
        self::assertFalse($id->equals(LessonProgressId::fromString('01900000-0000-7000-8000-000000004412')));
    }

    public function testLessonProgressIdRejectsNonUuidV7(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        LessonProgressId::fromString('01900000-0000-6000-8000-000000004413');
    }
}
