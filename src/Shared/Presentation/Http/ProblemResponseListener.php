<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Presentation\Http;

use Psr\Log\LoggerInterface;
use SovereignStack\Lms\Shared\Application\Exception\ApplicationException;
use SovereignStack\Lms\Shared\Application\Exception\ConflictException;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Domain\Exception\DomainException;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use SovereignStack\Lms\Shared\Infrastructure\Correlation\CorrelationIdHolder;
use SovereignStack\Lms\Shared\Infrastructure\Correlation\CorrelationIdSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ProblemResponseListener implements EventSubscriberInterface
{
    public function __construct(
        private ProblemDetailsFactory $factory,
        private CorrelationIdHolder $holder,
        private UuidGeneratorInterface $uuidGenerator,
        private LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $correlationId = $this->correlationId($request);
        $path = $request->getPathInfo();
        $throwable = $event->getThrowable();

        try {
            $response = $this->factory->fromThrowable($throwable, $correlationId, $path);
        } catch (\Throwable) {
            $response = new JsonResponse([
                'type' => 'https://sovereignstack.dev/problems/internal-error',
                'title' => 'Internal Server Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'An unexpected error occurred.',
                'instance' => $path,
                'correlation_id' => $correlationId,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
            $response->headers->set('Content-Type', ProblemDetailsFactory::CONTENT_TYPE);
        }

        $response->headers->set(CorrelationIdSubscriber::HEADER, $correlationId);
        $event->setResponse($response);

        $this->log($throwable, $response->getStatusCode(), $correlationId, $path);
    }

    private function correlationId(Request $request): string
    {
        $attribute = $request->attributes->get(CorrelationIdSubscriber::ATTRIBUTE);

        if (\is_string($attribute) && '' !== $attribute) {
            return $attribute;
        }

        $held = $this->holder->get();

        if (null !== $held && '' !== $held) {
            return $held;
        }

        $correlationId = $this->uuidGenerator->generate();
        $this->holder->set($correlationId);

        return $correlationId;
    }

    private function log(\Throwable $throwable, int $status, string $correlationId, string $path): void
    {
        try {
            if ($status >= Response::HTTP_INTERNAL_SERVER_ERROR) {
                $this->logger->error('Unhandled exception while processing request.', [
                    'exception' => $throwable,
                    'correlation_id' => $correlationId,
                    'path' => $path,
                    'status' => $status,
                ]);

                return;
            }

            if ($status >= Response::HTTP_BAD_REQUEST) {
                $this->logger->warning('Request rejected.', [
                    'correlation_id' => $correlationId,
                    'status' => $status,
                    'path' => $path,
                    'error_code' => $this->safeErrorCode($throwable),
                    'error_message' => $this->safeErrorMessage($throwable, $status),
                ]);
            }
        } catch (\Throwable) {
            // Logging must never break the error response.
        }
    }

    private function safeErrorCode(\Throwable $throwable): string
    {
        return match (true) {
            $throwable instanceof ValidationException => 'validation_failed',
            $throwable instanceof NotFoundException => 'not_found',
            $throwable instanceof ConflictException => 'conflict',
            $throwable instanceof ForbiddenException => 'forbidden',
            $throwable instanceof DomainException => 'domain_violation',
            $throwable instanceof ApplicationException => 'application_error',
            $throwable instanceof HttpExceptionInterface => 'http_error',
            default => 'unexpected',
        };
    }

    private function safeErrorMessage(\Throwable $throwable, int $status): string
    {
        if ($throwable instanceof ApplicationException || $throwable instanceof DomainException) {
            return $throwable->getMessage();
        }

        if ($throwable instanceof HttpExceptionInterface) {
            return Response::$statusTexts[$status] ?? 'HTTP error';
        }

        return 'The request could not be completed.';
    }
}
