<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Infrastructure\Storage;

use SovereignStack\Lms\Shared\Application\Exception\FileNotFoundException;
use SovereignStack\Lms\Shared\Application\Exception\FileValidationException;
use SovereignStack\Lms\Shared\Application\Storage\FileStorageInterface;
use SovereignStack\Lms\Shared\Domain\Clock\ClockInterface;
use SovereignStack\Lms\Shared\Domain\Uuid\UuidGeneratorInterface;

/**
 * Stores files on a private local filesystem outside the public web root.
 *
 * Physical paths are never returned to callers: store() yields an opaque,
 * date-partitioned storage key instead.
 */
final readonly class LocalFileStorage implements FileStorageInterface
{
    /**
     * @var array<string, string>
     */
    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'text/plain' => 'txt',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'video/mp4' => 'mp4',
    ];

    /**
     * @var list<string>
     */
    private const OFFICE_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private const KEY_PATTERN = '~^\d{4}/\d{2}/[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.(pdf|png|jpg|gif|webp|txt|docx|pptx|xlsx|mp4)$~D';

    public function __construct(
        private string $rootDirectory,
        private int $maxBytes,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
        if ('' === trim($rootDirectory)) {
            throw new \InvalidArgumentException('The storage root directory must not be empty.');
        }

        if ($maxBytes < 1) {
            throw new \InvalidArgumentException('The maximum file size must be at least one byte.');
        }
    }

    public function store(string $contents, string $originalFilename, string $mimeType): string
    {
        $extension = $this->validateFilename($originalFilename);
        $canonicalExtension = self::MIME_EXTENSIONS[$mimeType] ?? null;

        if (null === $canonicalExtension) {
            throw FileValidationException::forFile('The provided file type is not allowed.');
        }

        if ($extension !== $canonicalExtension) {
            throw FileValidationException::forFile('The file extension does not match the provided content type.');
        }

        if ('' === $contents) {
            throw FileValidationException::forFile('The file is empty.');
        }

        if (\strlen($contents) > $this->maxBytes) {
            throw FileValidationException::forFile('File exceeds the maximum allowed size.');
        }

        $detectedMimeType = (new \finfo(\FILEINFO_MIME_TYPE))->buffer($contents);

        if (!\is_string($detectedMimeType) || !$this->mimeMatches($mimeType, $detectedMimeType)) {
            throw FileValidationException::forFile('The file content does not match the declared content type.');
        }

        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $year = $now->format('Y');
        $month = $now->format('m');
        $directory = $this->rootDirectory.'/'.$year.'/'.$month;

        $this->ensureDirectory($directory);

        $filename = $this->uuidGenerator->generate().'.'.$canonicalExtension;
        $path = $directory.'/'.$filename;

        if (false === file_put_contents($path, $contents, \LOCK_EX)) {
            throw new \RuntimeException('Unable to store the file.');
        }

        @chmod($path, 0640);

        return $year.'/'.$month.'/'.$filename;
    }

    public function read(string $storageKey): string
    {
        $path = $this->resolvePath($storageKey);

        if (null === $path) {
            throw new FileNotFoundException();
        }

        $contents = file_get_contents($path);

        if (false === $contents) {
            throw new FileNotFoundException();
        }

        return $contents;
    }

    public function delete(string $storageKey): void
    {
        $path = $this->resolvePath($storageKey);

        if (null === $path) {
            return;
        }

        if (!@unlink($path)) {
            throw new \RuntimeException('Unable to delete the file.');
        }
    }

    private function validateFilename(string $filename): string
    {
        if ('' === $filename) {
            throw FileValidationException::forFile('The file name must not be empty.');
        }

        if (\strlen($filename) > 255) {
            throw FileValidationException::forFile('The file name must not exceed 255 characters.');
        }

        if (str_contains($filename, "\0")) {
            throw FileValidationException::forFile('The file name contains invalid characters.');
        }

        if (str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw FileValidationException::forFile('The file name must not contain directory separators.');
        }

        $extension = strtolower(pathinfo($filename, \PATHINFO_EXTENSION));

        if ('' === $extension) {
            throw FileValidationException::forFile('A file extension is required.');
        }

        if ('jpeg' === $extension) {
            $extension = 'jpg';
        }

        if (!\in_array($extension, self::MIME_EXTENSIONS, true)) {
            throw FileValidationException::forFile('The file type is not allowed.');
        }

        return $extension;
    }

    private function mimeMatches(string $declaredMimeType, string $detectedMimeType): bool
    {
        if ($declaredMimeType === $detectedMimeType) {
            return true;
        }

        return 'application/zip' === $detectedMimeType
            && \in_array($declaredMimeType, self::OFFICE_MIME_TYPES, true);
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create the storage directory.');
        }
    }

    private function resolvePath(string $storageKey): ?string
    {
        if (1 !== preg_match(self::KEY_PATTERN, $storageKey) || str_contains($storageKey, '..')) {
            throw FileValidationException::forFile('The storage key is invalid.');
        }

        $realRoot = realpath($this->rootDirectory);

        if (false === $realRoot) {
            return null;
        }

        $realPath = realpath($this->rootDirectory.'/'.$storageKey);

        if (false === $realPath || !is_file($realPath)) {
            return null;
        }

        if (!str_starts_with($realPath, rtrim($realRoot, \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR)) {
            throw FileValidationException::forFile('The storage key is invalid.');
        }

        return $realPath;
    }
}
