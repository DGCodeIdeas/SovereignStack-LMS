<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Uuid;

use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final readonly class SymfonyUidUuidGenerator implements UuidGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v7()->toRfc4122();
    }
}
