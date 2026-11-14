<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Shared\Exception;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Shared\Application\Exception\ApplicationException;
use SovereignStack\Lms\Shared\Application\Exception\ConflictException;
use SovereignStack\Lms\Shared\Application\Exception\FileNotFoundException;
use SovereignStack\Lms\Shared\Application\Exception\FileValidationException;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Domain\Exception\DomainException;
use SovereignStack\Lms\Shared\Domain\Result\ErrorCode;

/**
 * @internal
 *
 * @coversNothing
 */
final class ExceptionTest extends TestCase
{
    public function testApplicationExceptionDefaultsToApplicationError(): void
    {
        $exception = new ApplicationException('Broken.');

        self::assertSame('Broken.', $exception->getMessage());
        self::assertSame(ErrorCode::APPLICATION_ERROR, $exception->errorCode());
    }

    public function testDomainExceptionDefaultsToDomainViolation(): void
    {
        $exception = new DomainException('Invariant broken.');

        self::assertSame('Invariant broken.', $exception->getMessage());
        self::assertSame(ErrorCode::DOMAIN_VIOLATION, $exception->errorCode());
    }

    public function testDomainExceptionAcceptsCustomErrorCode(): void
    {
        $exception = new DomainException('Forbidden.', ErrorCode::FORBIDDEN);

        self::assertSame(ErrorCode::FORBIDDEN, $exception->errorCode());
    }

    public function testValidationExceptionCarriesErrors(): void
    {
        $exception = ValidationException::withErrors(['email' => ['Invalid.']], 'Bad input.');

        self::assertSame(ErrorCode::VALIDATION_FAILED, $exception->errorCode());
        self::assertSame('Bad input.', $exception->getMessage());
        self::assertSame(['email' => ['Invalid.']], $exception->errors());
    }

    public function testValidationExceptionDefaults(): void
    {
        $exception = new ValidationException();

        self::assertSame(ErrorCode::VALIDATION_FAILED, $exception->errorCode());
        self::assertSame('Validation failed.', $exception->getMessage());
        self::assertSame([], $exception->errors());
    }

    public function testNotFoundExceptionDefaults(): void
    {
        $exception = new NotFoundException();

        self::assertSame(ErrorCode::NOT_FOUND, $exception->errorCode());
        self::assertSame('The requested resource was not found.', $exception->getMessage());
    }

    public function testConflictExceptionDefaults(): void
    {
        $exception = new ConflictException();

        self::assertSame(ErrorCode::CONFLICT, $exception->errorCode());
        self::assertSame('The request conflicts with the current state of the resource.', $exception->getMessage());
    }

    public function testForbiddenExceptionDefaults(): void
    {
        $exception = new ForbiddenException();

        self::assertSame(ErrorCode::FORBIDDEN, $exception->errorCode());
        self::assertSame('You are not allowed to perform this action.', $exception->getMessage());
    }

    public function testFileValidationExceptionBuildsFileError(): void
    {
        $exception = FileValidationException::forFile('The file is not allowed.');

        self::assertInstanceOf(ValidationException::class, $exception);
        self::assertSame(ErrorCode::VALIDATION_FAILED, $exception->errorCode());
        self::assertSame(['file' => ['The file is not allowed.']], $exception->errors());
    }

    public function testFileNotFoundExceptionDefaults(): void
    {
        $exception = new FileNotFoundException();

        self::assertInstanceOf(NotFoundException::class, $exception);
        self::assertSame(ErrorCode::NOT_FOUND, $exception->errorCode());
        self::assertSame('The requested file was not found.', $exception->getMessage());
    }
}
