<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ModuleBoundariesTest extends TestCase
{
    private const SRC_DIR = __DIR__ . '/../../src';

    public function testDomainLayerDoesNotDependOnSymfony(): void
    {
        $violations = $this->findUseStatements(
            self::SRC_DIR,
            ['*/Domain/*'],
            ['Symfony\\'],
        );

        $this->assertSame(
            [],
            $violations,
            "Domain layer must not depend on Symfony. Violations:\n" . implode("\n", $violations),
        );
    }

    public function testDomainLayerDoesNotDependOnDoctrine(): void
    {
        $violations = $this->findUseStatements(
            self::SRC_DIR,
            ['*/Domain/*'],
            ['Doctrine\\'],
        );

        $this->assertSame(
            [],
            $violations,
            "Domain layer must not depend on Doctrine. Violations:\n" . implode("\n", $violations),
        );
    }

    public function testDomainLayerDoesNotDependOnHttpInfrastructure(): void
    {
        $violations = $this->findUseStatements(
            self::SRC_DIR,
            ['*/Domain/*'],
            ['Symfony\\Component\\HttpFoundation', 'Symfony\\Component\\HttpKernel'],
        );

        $this->assertSame(
            [],
            $violations,
            "Domain layer must not depend on HTTP infrastructure. Violations:\n" . implode("\n", $violations),
        );
    }

    public function testNoDglabDependencies(): void
    {
        $violations = $this->findUseStatements(
            self::SRC_DIR,
            ['*'],
            ['SovereignStack\\Internal', 'SovereignStack\\Core', 'SovereignStack\\Hub'],
        );

        $this->assertSame(
            [],
            $violations,
            "No DGLab dependencies allowed. Violations:\n" . implode("\n", $violations),
        );
    }

    public function testNoDoctrineOrmImport(): void
    {
        $violations = $this->findUseStatements(
            self::SRC_DIR,
            ['*'],
            ['Doctrine\\ORM'],
        );

        $this->assertSame(
            [],
            $violations,
            "No Doctrine ORM imports allowed. Violations:\n" . implode("\n", $violations),
        );
    }

    public function testModuleDirectoriesExist(): void
    {
        $modules = ['Shared', 'Identity', 'Organisation', 'Course', 'Enrollment', 'Learning', 'Administration'];

        foreach ($modules as $module) {
            $path = self::SRC_DIR . '/' . $module;
            $this->assertDirectoryExists(
                $path,
                "Module directory {$module} must exist at src/{$module}",
            );
        }
    }

    /**
     * @param array<string> $dirPatterns Glob patterns relative to scan root
     * @param array<string> $forbiddenNamespacePrefixes
     * @return array<string> Violation descriptions
     */
    private function findUseStatements(string $root, array $dirPatterns, array $forbiddenNamespacePrefixes): array
    {
        $violations = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($root . '/', '', $file->getPathname());

            $matchesDirPattern = false;
            foreach ($dirPatterns as $pattern) {
                if (fnmatch($pattern, $relativePath)) {
                    $matchesDirPattern = true;
                    break;
                }
            }
            if (!$matchesDirPattern) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!str_starts_with($line, 'use ')) {
                    continue;
                }

                foreach ($forbiddenNamespacePrefixes as $prefix) {
                    if (str_contains($line, $prefix)) {
                        $violations[] = "{$relativePath}: {$line}";
                        break;
                    }
                }
            }
        }

        return $violations;
    }
}
