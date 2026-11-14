<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Shared\Storage;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Shared\Application\Exception\FileNotFoundException;
use SovereignStack\Lms\Shared\Application\Exception\FileValidationException;
use SovereignStack\Lms\Shared\Infrastructure\Clock\SystemClock;
use SovereignStack\Lms\Shared\Infrastructure\Storage\LocalFileStorage;
use SovereignStack\Lms\Shared\Infrastructure\Uuid\SymfonyUidUuidGenerator;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 *
 * @coversNothing
 */
final class LocalFileStorageTest extends TestCase
{
    private const KEY_PATTERN = '~^\d{4}/\d{2}/[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.(pdf|png|jpg|gif|webp|txt|docx|pptx|xlsx|mp4)$~';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/lms-file-storage-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    public function testStoreReadAndDeleteTextFile(): void
    {
        $storage = $this->storage(4096);

        $key = $storage->store('hello world', 'notes.txt', 'text/plain');

        self::assertMatchesRegularExpression(self::KEY_PATTERN, $key);
        self::assertFileExists($this->root.'/'.$key);
        self::assertSame('hello world', $storage->read($key));

        $storage->delete($key);

        self::assertFileDoesNotExist($this->root.'/'.$key);
    }

    public function testStoreNormalizesJpegExtension(): void
    {
        $storage = $this->storage(4096);
        $jpeg = hex2bin('ffd8ffe000104a46494600010100000100010000ffd9');

        self::assertIsString($jpeg);

        $key = $storage->store($jpeg, 'photo.jpeg', 'image/jpeg');

        self::assertStringEndsWith('.jpg', $key);
        self::assertSame($jpeg, $storage->read($key));
    }

    public function testStoreRejectsPathTraversalFilenames(): void
    {
        $storage = $this->storage(4096);

        foreach (['../../etc/passwd', '/etc/passwd', '..\..\etc\passwd.txt', '..', 'nested/dir.txt'] as $filename) {
            try {
                $storage->store('content', $filename, 'text/plain');
                self::fail(sprintf('Expected rejection of filename "%s".', $filename));
            } catch (FileValidationException $exception) {
                self::assertSame(['file'], array_keys($exception->errors()));
            }
        }
    }

    public function testReadRejectsMalformedAndTraversalKeys(): void
    {
        $storage = $this->storage(4096);

        $keys = [
            '../../etc/passwd',
            '/etc/passwd',
            '2026/11/../../etc/passwd.txt',
            '2026/11/0190f0a0-0000-7000-8000-000000000000.txt/../../x',
            'not-a-key',
            '2026/11/0190f0a0-0000-7000-8000-000000000000',
            '2026/11/0190f0a0-0000-4000-8000-000000000000.txt',
            '2026/11/0190f0a0-0000-7000-8000-000000000000.php',
        ];

        foreach ($keys as $key) {
            try {
                $storage->read($key);
                self::fail(sprintf('Expected rejection of key "%s".', $key));
            } catch (FileValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testDeleteRejectsMalformedKeys(): void
    {
        $storage = $this->storage(4096);

        $this->expectException(FileValidationException::class);

        $storage->delete('../../etc/passwd');
    }

    public function testStoreRejectsExecutableExtensions(): void
    {
        $storage = $this->storage(4096);

        foreach (['shell.php', 'template.phtml', 'bundle.phar', 'payload.exe', 'script.sh'] as $filename) {
            try {
                $storage->store('content', $filename, 'text/plain');
                self::fail(sprintf('Expected rejection of filename "%s".', $filename));
            } catch (FileValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testStoreRejectsMimeMismatch(): void
    {
        $storage = $this->storage(4096);

        $this->expectException(FileValidationException::class);

        $storage->store('<?php echo "pwned";', 'document.pdf', 'application/pdf');
    }

    public function testStoreRejectsDisallowedMimeType(): void
    {
        $storage = $this->storage(4096);

        $this->expectException(FileValidationException::class);

        $storage->store('content', 'document.txt', 'text/html');
    }

    public function testStoreRejectsExtensionMismatch(): void
    {
        $storage = $this->storage(4096);

        $this->expectException(FileValidationException::class);

        $storage->store('hello world', 'document.md', 'text/plain');
    }

    public function testStoreRejectsEmptyContent(): void
    {
        $storage = $this->storage(4096);

        $this->expectException(FileValidationException::class);

        $storage->store('', 'notes.txt', 'text/plain');
    }

    public function testStoreRejectsOversizedFile(): void
    {
        $storage = $this->storage(4);

        try {
            $storage->store('too large', 'notes.txt', 'text/plain');
            self::fail('Expected the size limit to be enforced.');
        } catch (FileValidationException $exception) {
            self::assertSame(['file' => ['File exceeds the maximum allowed size.']], $exception->errors());
        }
    }

    public function testReadAfterDeleteThrowsFileNotFound(): void
    {
        $storage = $this->storage(4096);
        $key = $storage->store('hello world', 'notes.txt', 'text/plain');

        $storage->delete($key);

        $this->expectException(FileNotFoundException::class);

        $storage->read($key);
    }

    public function testDeleteOfMissingFileIsNoOp(): void
    {
        $storage = $this->storage(4096);
        $key = $storage->store('hello world', 'notes.txt', 'text/plain');

        $storage->delete($key);
        $storage->delete($key);

        self::addToAssertionCount(1);
    }

    public function testConstructorRejectsEmptyRootDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LocalFileStorage('', 1024, new SymfonyUidUuidGenerator(), new SystemClock());
    }

    public function testConstructorRejectsNonPositiveMaxBytes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LocalFileStorage($this->root, 0, new SymfonyUidUuidGenerator(), new SystemClock());
    }

    private function storage(int $maxBytes): LocalFileStorage
    {
        return new LocalFileStorage($this->root, $maxBytes, new SymfonyUidUuidGenerator(), new SystemClock());
    }
}
