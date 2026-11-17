<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Enrollment;

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Container\ContainerInterface;
use SovereignStack\Lms\Enrollment\Application\Handler\CancelEnrollmentHandler;
use SovereignStack\Lms\Enrollment\Application\Handler\CompleteEnrollmentHandler;
use SovereignStack\Lms\Enrollment\Application\Handler\EnrollHandler;
use SovereignStack\Lms\Enrollment\Application\Handler\GetEnrollmentHandler;
use SovereignStack\Lms\Enrollment\Application\Handler\ListEnrollmentsHandler;
use SovereignStack\Lms\Enrollment\Application\Handler\RecordLessonProgressHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class EnrollmentWiringTest extends KernelTestCase
{
    /**
     * @param class-string $handler
     */
    #[DataProvider('handlerProvider')]
    public function testHandlerIsFullyWired(string $handler): void
    {
        self::bootKernel();

        $container = self::getContainer();
        self::assertInstanceOf(ContainerInterface::class, $container);
        self::assertInstanceOf($handler, $container->get($handler));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function handlerProvider(): iterable
    {
        yield 'enroll' => [EnrollHandler::class];

        yield 'cancel' => [CancelEnrollmentHandler::class];

        yield 'record progress' => [RecordLessonProgressHandler::class];

        yield 'complete' => [CompleteEnrollmentHandler::class];

        yield 'get' => [GetEnrollmentHandler::class];

        yield 'list' => [ListEnrollmentsHandler::class];
    }
}
