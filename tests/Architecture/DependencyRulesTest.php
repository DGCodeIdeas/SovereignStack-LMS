<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Architecture;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 *
 * @coversNothing
 */
final class DependencyRulesTest extends TestCase
{
    public function testFrameworkBundleConstraintAllows74AndNot80(): void
    {
        $require = self::requirePackages();

        self::assertArrayHasKey('symfony/framework-bundle', $require);
        self::assertMatchesRegularExpression(
            '/^(\^7\.4|7\.4\.\*|>=7\.4 <8\.0)$/',
            $require['symfony/framework-bundle'],
        );
    }

    public function testRequiredDoctrineAndMessengerConstraints(): void
    {
        $require = self::requirePackages();

        self::assertSame('^4.5', $require['doctrine/dbal'] ?? null);
        self::assertSame('^3.4', $require['doctrine/doctrine-bundle'] ?? null);
        self::assertSame('^7.4', $require['symfony/doctrine-messenger'] ?? null);
    }

    public function testDoctrineOrmIsAbsent(): void
    {
        self::assertArrayNotHasKey('doctrine/orm', self::requirePackages());
        self::assertArrayNotHasKey('doctrine/orm', self::requireDevPackages());
    }

    public function testDoctrineConfigExistsWithoutOrmSection(): void
    {
        $path = self::root().'/config/packages/doctrine.yaml';
        self::assertFileExists($path);

        $config = Yaml::parseFile($path);
        self::assertIsArray($config);
        self::assertArrayNotHasKey('orm', $config);
    }

    public function testRequiredBundlesAreEnabled(): void
    {
        $path = self::root().'/config/bundles.php';
        self::assertFileExists($path);

        $bundles = require $path;
        self::assertIsArray($bundles);
        self::assertArrayHasKey(FrameworkBundle::class, $bundles);
        self::assertArrayHasKey(DoctrineBundle::class, $bundles);
        self::assertArrayHasKey(DoctrineMigrationsBundle::class, $bundles);
    }

    public function testComposerLockKeepsSymfonyTreeOn74(): void
    {
        $violations = [];

        foreach (self::lockPackages() as $package) {
            $name = $package['name'] ?? null;
            $version = $package['version'] ?? null;

            if (!\is_string($name) || !\is_string($version) || !str_starts_with($name, 'symfony/')) {
                continue;
            }

            if (str_ends_with($name, '-contracts') || 'symfony/maker-bundle' === $name || 'symfony/monolog-bundle' === $name) {
                continue;
            }

            if (str_starts_with($name, 'symfony/polyfill-')) {
                continue;
            }

            if (!str_starts_with($version, 'v7.4.') && !str_starts_with($version, '7.4.')) {
                $violations[] = sprintf('%s is locked at %s', $name, $version);
            }
        }

        self::assertSame([], $violations, implode("\n", $violations));
    }

    public function testComposerLockDoesNotContainDoctrineOrm(): void
    {
        foreach (self::lockPackages() as $package) {
            if ('doctrine/orm' === ($package['name'] ?? null)) {
                self::fail('composer.lock contains doctrine/orm.');
            }
        }

        self::addToAssertionCount(1);
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    /** @return array<string, mixed> */
    private static function composerArray(): array
    {
        $path = self::root().'/composer.json';
        $contents = file_get_contents($path);
        if (false === $contents) {
            self::fail(sprintf('Unable to read "%s"', $path));
        }

        $composer = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);

        return $composer;
    }

    /** @return array<string, string> */
    private static function requirePackages(): array
    {
        return self::stringPackages(self::composerArray()['require'] ?? []);
    }

    /** @return array<string, string> */
    private static function requireDevPackages(): array
    {
        return self::stringPackages(self::composerArray()['require-dev'] ?? []);
    }

    /** @return array<string, string> */
    private static function stringPackages(mixed $packages): array
    {
        self::assertIsArray($packages);

        $result = [];
        foreach ($packages as $name => $constraint) {
            if (\is_string($name) && \is_string($constraint)) {
                $result[$name] = $constraint;
            }
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    private static function lockPackages(): array
    {
        $path = self::root().'/composer.lock';
        self::assertFileExists($path);

        $contents = file_get_contents($path);
        if (false === $contents) {
            self::fail(sprintf('Unable to read "%s"', $path));
        }

        $lock = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($lock);

        $packages = $lock['packages'] ?? [];
        $devPackages = $lock['packages-dev'] ?? [];
        self::assertIsArray($packages);
        self::assertIsArray($devPackages);

        $result = [];
        foreach (array_merge($packages, $devPackages) as $package) {
            if (\is_array($package)) {
                $result[] = $package;
            }
        }

        return $result;
    }
}
