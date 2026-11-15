<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Persistence;

final class DatabaseValue
{
    /**
     * @param array<string, mixed> $row
     */
    public static function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;

        if (!\is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Expected column "%s" to be a string, got %s.', $column, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function nullableString(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;

        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Expected column "%s" to be a string or null, got %s.', $column, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function int(array $row, string $column): int
    {
        $value = $row[$column] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (\is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        throw new \UnexpectedValueException(sprintf('Expected column "%s" to be numeric, got %s.', $column, get_debug_type($value)));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function encodeJson(array $data): string
    {
        return json_encode($data, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodeJson(?string $json): array
    {
        if (null === $json || '' === $json) {
            return [];
        }

        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            throw new \UnexpectedValueException('Expected a JSON object, got '.get_debug_type($decoded).'.');
        }

        $result = [];

        foreach ($decoded as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
