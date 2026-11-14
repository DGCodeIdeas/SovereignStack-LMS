<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 *
 * @coversNothing
 */
final class ModuleBoundariesTest extends TestCase
{
    public function testDomainLayerHasNoFrameworkOrMutableClockDependencies(): void
    {
        $violations = [];

        foreach (self::phpFiles(self::root().'/src') as $file) {
            $path = self::path($file);
            if (!str_contains($path, '/Domain/')) {
                continue;
            }

            $source = self::source($file);

            foreach (['use Symfony\\', 'use Doctrine\\'] as $forbidden) {
                if (str_contains($source, $forbidden)) {
                    $violations[] = sprintf('%s contains forbidden import "%s"', $path, $forbidden);
                }
            }

            if (1 === preg_match('/new\s+DateTime\s*\(/', $source)) {
                $violations[] = sprintf('%s instantiates mutable DateTime', $path);
            }

            if (str_contains($source, 'random_bytes(')) {
                $violations[] = sprintf('%s calls random_bytes()', $path);
            }
        }

        self::assertSame([], $violations, implode("\n", $violations));
    }

    public function testApplicationLayerHasNoHttpFrameworkDependencies(): void
    {
        $violations = [];

        foreach (self::phpFiles(self::root().'/src') as $file) {
            $path = self::path($file);
            if (!str_contains($path, '/Application/')) {
                continue;
            }

            $source = self::source($file);

            foreach (['use Symfony\Component\HttpFoundation', 'use Symfony\Component\HttpKernel'] as $forbidden) {
                if (str_contains($source, $forbidden)) {
                    $violations[] = sprintf('%s contains forbidden import "%s"', $path, $forbidden);
                }
            }
        }

        self::assertSame([], $violations, implode("\n", $violations));
    }

    public function testDgLabNamespacesAreNotUsed(): void
    {
        $violations = [];

        foreach (self::phpFiles(self::root().'/src') as $file) {
            $source = self::source($file);
            $path = self::path($file);

            foreach (['use SovereignStack\Internal', 'use SovereignStack\Core', 'use SovereignStack\Hub'] as $forbidden) {
                if (str_contains($source, $forbidden)) {
                    $violations[] = sprintf('%s contains forbidden DGLab import "%s"', $path, $forbidden);
                }
            }
        }

        self::assertSame([], $violations, implode("\n", $violations));
    }

    public function testDoctrineOrmIsNotUsed(): void
    {
        $violations = [];

        foreach (self::phpFiles(self::root().'/src') as $file) {
            $source = self::source($file);

            if (str_contains($source, 'use Doctrine\ORM')) {
                $violations[] = sprintf('%s contains forbidden import "use Doctrine\ORM"', self::path($file));
            }
        }

        self::assertSame([], $violations, implode("\n", $violations));
    }

    public function testModulesOnlyReferenceEachOtherThroughContracts(): void
    {
        $violations = [];
        $src = self::root().'/src';
        $modules = [];

        foreach (self::entries($src) as $entry) {
            if ('Shared' === $entry || !is_dir($src.'/'.$entry)) {
                continue;
            }

            $modules[] = $entry;
        }

        foreach ($modules as $module) {
            foreach (self::phpFiles($src.'/'.$module) as $file) {
                $source = self::source($file);

                if (0 === preg_match_all('/use\s+SovereignStack\\\Lms\\\([A-Za-z0-9_]+)\\\/', $source, $matches)) {
                    continue;
                }

                foreach (array_unique($matches[1]) as $referencedModule) {
                    if (!is_string($referencedModule) || $referencedModule === $module || 'Shared' === $referencedModule) {
                        continue;
                    }

                    $path = self::path($file);
                    if (str_contains($path, '/Contract/')) {
                        continue;
                    }

                    $violations[] = sprintf('%s imports "%s" outside a contract boundary', $path, $referencedModule);
                }
            }
        }

        self::assertSame([], $violations, implode("\n", $violations));
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    /** @return list<\SplFileInfo> */
    private static function phpFiles(string $directory): array
    {
        $files = [];

        foreach (Finder::create()->in($directory)->files()->name('*.php')->sortByName() as $file) {
            self::assertInstanceOf(\SplFileInfo::class, $file);
            $files[] = $file;
        }

        return $files;
    }

    /** @return list<string> */
    private static function entries(string $directory): array
    {
        $entries = scandir($directory);
        if (false === $entries) {
            self::fail(sprintf('Unable to read directory "%s"', $directory));
        }

        $result = [];
        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $result[] = $entry;
        }

        return $result;
    }

    private static function source(\SplFileInfo $file): string
    {
        $contents = file_get_contents($file->getPathname());
        if (false === $contents) {
            self::fail(sprintf('Unable to read file "%s"', $file->getPathname()));
        }

        return $contents;
    }

    private static function path(\SplFileInfo $file): string
    {
        return str_replace('\\', '/', $file->getPathname());
    }
}
