<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Functional\Fixtures;

use SovereignStack\Lms\Shared\Application\Exception\ConflictException;
use SovereignStack\Lms\Shared\Application\Exception\ForbiddenException;
use SovereignStack\Lms\Shared\Application\Exception\NotFoundException;
use SovereignStack\Lms\Shared\Application\Exception\ValidationException;
use SovereignStack\Lms\Shared\Domain\Exception\DomainException;
use Symfony\Component\Routing\Attribute\Route;

final class ProblemProbeController
{
    #[Route('/_test/problem/{type}', name: 'test_problem_probe', methods: ['GET'])]
    public function __invoke(string $type): never
    {
        throw match ($type) {
            'validation' => new ValidationException(['email' => ['Email is invalid.']], 'Validation failed.'),
            'not_found' => new NotFoundException(),
            'conflict' => new ConflictException(),
            'forbidden' => new ForbiddenException(),
            'domain' => new DomainException('A domain rule was violated.'),
            'unexpected' => new \RuntimeException('SECRET_INTERNAL_DETAIL /srv/private/path.php'),
            default => new NotFoundException(),
        };
    }
}
