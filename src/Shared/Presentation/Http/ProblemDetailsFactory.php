<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Presentation\Http;

use SovereignStack\Lms\Shared\Application\Exception\ApplicationException;
use SovereignStack\Lms\Shared\Application\Exception\ConflictException;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Domain\Exception\DomainException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final readonly class ProblemDetailsFactory
{
    public const CONTENT_TYPE = 'application/problem+json; charset=utf-8';

    private const TYPE_BASE = 'https://sovereignstack.dev/problems/';

    private const GENERIC_DETAIL = 'The request could not be completed.';

    /**
     * @var array<int, string>
     */
    private const SAFE_HTTP_DETAILS = [
        400 => 'The request could not be processed as submitted.',
        401 => 'Authentication is required to access this resource.',
        403 => 'You are not allowed to perform this action.',
        404 => 'The requested resource was not found.',
        405 => 'The HTTP method is not allowed for this resource.',
        409 => 'The request conflicts with the current state of the resource.',
        429 => 'Too many requests. Please try again later.',
    ];

    public function fromThrowable(\Throwable $exception, string $correlationId, string $instance): JsonResponse
    {
        $problem = $this->map($exception);

        $payload = [
            'type' => self::TYPE_BASE.$problem['type'],
            'title' => $problem['title'],
            'status' => $problem['status'],
            'detail' => $problem['detail'],
            'instance' => $instance,
            'correlation_id' => $correlationId,
        ];

        if ($exception instanceof ValidationException) {
            $payload['errors'] = $exception->errors();
        }

        $response = new JsonResponse($payload, $problem['status']);
        $response->headers->set('Content-Type', self::CONTENT_TYPE);

        return $response;
    }

    /**
     * @return array{status: int, type: string, title: string, detail: string}
     */
    private function map(\Throwable $exception): array
    {
        if ($exception instanceof ValidationException) {
            return [
                'status' => Response::HTTP_BAD_REQUEST,
                'type' => 'validation-failed',
                'title' => 'Validation failed',
                'detail' => $exception->getMessage(),
            ];
        }

        if ($exception instanceof NotFoundException) {
            return [
                'status' => Response::HTTP_NOT_FOUND,
                'type' => 'not-found',
                'title' => 'Not Found',
                'detail' => $exception->getMessage(),
            ];
        }

        if ($exception instanceof ConflictException) {
            return [
                'status' => Response::HTTP_CONFLICT,
                'type' => 'conflict',
                'title' => 'Conflict',
                'detail' => $exception->getMessage(),
            ];
        }

        if ($exception instanceof ForbiddenException) {
            return [
                'status' => Response::HTTP_FORBIDDEN,
                'type' => 'forbidden',
                'title' => 'Forbidden',
                'detail' => $exception->getMessage(),
            ];
        }

        if ($exception instanceof DomainException) {
            return [
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'type' => 'domain-violation',
                'title' => 'Unprocessable Entity',
                'detail' => $exception->getMessage(),
            ];
        }

        if ($exception instanceof ApplicationException) {
            return [
                'status' => Response::HTTP_BAD_REQUEST,
                'type' => 'application-error',
                'title' => 'Bad Request',
                'detail' => $exception->getMessage(),
            ];
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return [
                'status' => $status,
                'type' => 'http-'.$status,
                'title' => Response::$statusTexts[$status] ?? 'Error',
                'detail' => self::SAFE_HTTP_DETAILS[$status] ?? self::GENERIC_DETAIL,
            ];
        }

        return [
            'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'type' => 'internal-error',
            'title' => 'Internal Server Error',
            'detail' => 'An unexpected error occurred.',
        ];
    }
}
