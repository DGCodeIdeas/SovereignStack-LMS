<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Shared\Application\Storage;

interface FileStorageInterface
{
    public function store(string $contents, string $originalFilename, string $mimeType): string;

    public function read(string $storageKey): string;

    public function delete(string $storageKey): void;
}
