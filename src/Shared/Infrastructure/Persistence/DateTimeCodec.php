<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Persistence;

final class DateTimeCodec
{
    private const FORMAT = 'Y-m-d H:i:s.u';

    public static function toDatabase(\DateTimeImmutable $value): string
    {
        return $value->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT);
    }

    public static function toDatabaseNullable(?\DateTimeImmutable $value): ?string
    {
        return null === $value ? null : self::toDatabase($value);
    }

    public static function fromDatabase(string $value): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat(self::FORMAT, $value, new \DateTimeZone('UTC'));

        if (false === $parsed) {
            throw new \UnexpectedValueException(sprintf('Unable to parse datetime "%s".', $value));
        }

        return $parsed;
    }

    public static function fromDatabaseNullable(?string $value): ?\DateTimeImmutable
    {
        return null === $value ? null : self::fromDatabase($value);
    }
}
