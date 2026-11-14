<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Shared\Result;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Shared\Domain\Result\Error;
use SovereignStack\Lms\Shared\Domain\Result\ErrorCode;
use SovereignStack\Lms\Shared\Domain\Result\Result;

/**
 * @internal
 *
 * @coversNothing
 */
final class ResultTest extends TestCase
{
    public function testSuccessExposesValue(): void
    {
        $result = Result::success('payload');

        self::assertTrue($result->isSuccess());
        self::assertFalse($result->isFailure());
        self::assertSame('payload', $result->value());
    }

    public function testSuccessDefaultsToNull(): void
    {
        $result = Result::success();

        self::assertTrue($result->isSuccess());
        self::assertNull($result->value());
    }

    public function testFailureExposesError(): void
    {
        $error = new Error(ErrorCode::NOT_FOUND, 'Missing.');
        $result = Result::failure($error);

        self::assertTrue($result->isFailure());
        self::assertFalse($result->isSuccess());
        self::assertSame($error, $result->error());
    }

    public function testValueOnFailureThrowsLogicException(): void
    {
        $result = Result::failure(new Error(ErrorCode::CONFLICT, 'Conflict.'));

        $this->expectException(\LogicException::class);

        $result->value();
    }

    public function testErrorOnSuccessThrowsLogicException(): void
    {
        $result = Result::success('payload');

        $this->expectException(\LogicException::class);

        $result->error();
    }

    public function testErrorCarriesDetails(): void
    {
        $error = new Error(ErrorCode::VALIDATION_FAILED, 'Invalid.', ['field' => ['Required.']]);

        self::assertSame(['field' => ['Required.']], $error->details);
    }

    public function testErrorCodeValuesAreStable(): void
    {
        self::assertSame('validation_failed', ErrorCode::VALIDATION_FAILED->value);
        self::assertSame('not_found', ErrorCode::NOT_FOUND->value);
        self::assertSame('conflict', ErrorCode::CONFLICT->value);
        self::assertSame('forbidden', ErrorCode::FORBIDDEN->value);
        self::assertSame('domain_violation', ErrorCode::DOMAIN_VIOLATION->value);
        self::assertSame('application_error', ErrorCode::APPLICATION_ERROR->value);
        self::assertSame('unexpected', ErrorCode::UNEXPECTED->value);
        self::assertCount(7, ErrorCode::cases());
    }
}
