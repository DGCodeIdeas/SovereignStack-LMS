<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration;

use SovereignStack\Lms\Shared\Application\Transaction\TransactionManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class TransactionManagerTest extends DatabaseIntegrationTestCase
{
    private TransactionManagerInterface $transactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate('organisations');

        $transactions = self::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);

        $this->transactions = $transactions;
    }

    public function testCommitPersistsChangesAndReturnsResult(): void
    {
        $slug = $this->slug('commit');

        $result = $this->transactions->transactional(function () use ($slug): string {
            $this->insertOrganisation($slug);

            return 'committed';
        });

        self::assertSame('committed', $result);
        self::assertSame(1, $this->fetchInt('SELECT COUNT(*) FROM organisations WHERE slug = :slug', ['slug' => $slug]));
    }

    public function testExceptionRollsBackAndRethrows(): void
    {
        $slug = $this->slug('rollback');
        $caught = null;

        try {
            $this->transactions->transactional(function () use ($slug): void {
                $this->insertOrganisation($slug);

                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(\RuntimeException::class, $caught);
        self::assertSame('boom', $caught->getMessage());
        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM organisations WHERE slug = :slug', ['slug' => $slug]));
    }

    public function testNestedCommitPersistsBothChanges(): void
    {
        $outer = $this->slug('outer');
        $inner = $this->slug('inner');

        $this->transactions->transactional(function () use ($outer, $inner): void {
            $this->insertOrganisation($outer);

            $this->transactions->transactional(function () use ($inner): void {
                $this->insertOrganisation($inner);
            });
        });

        self::assertSame(1, $this->fetchInt('SELECT COUNT(*) FROM organisations WHERE slug = :slug', ['slug' => $outer]));
        self::assertSame(1, $this->fetchInt('SELECT COUNT(*) FROM organisations WHERE slug = :slug', ['slug' => $inner]));
    }

    public function testNestedRollbackDiscardsInnerChangesAndKeepsOuterChanges(): void
    {
        $outer = $this->slug('outer');
        $inner = $this->slug('inner');

        $this->transactions->transactional(function () use ($outer, $inner): void {
            $this->insertOrganisation($outer);

            try {
                $this->transactions->transactional(function () use ($inner): void {
                    $this->insertOrganisation($inner);

                    throw new \RuntimeException('inner failure');
                });
            } catch (\RuntimeException) {
                // The outer transaction intentionally survives the inner failure.
            }

            $this->insertOrganisation($this->slug('outer-after'));
        });

        self::assertSame(2, $this->fetchInt('SELECT COUNT(*) FROM organisations'));
        self::assertSame(1, $this->fetchInt('SELECT COUNT(*) FROM organisations WHERE slug = :slug', ['slug' => $outer]));
        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM organisations WHERE slug = :slug', ['slug' => $inner]));
    }

    public function testUncaughtNestedFailureRollsBackOuterAndInnerChanges(): void
    {
        $outer = $this->slug('outer');
        $inner = $this->slug('inner');
        $caught = null;

        try {
            $this->transactions->transactional(function () use ($outer, $inner): void {
                $this->insertOrganisation($outer);

                $this->transactions->transactional(function () use ($inner): void {
                    $this->insertOrganisation($inner);

                    throw new \RuntimeException('nested failure');
                });
            });
        } catch (\RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(\RuntimeException::class, $caught);
        self::assertSame('nested failure', $caught->getMessage());
        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM organisations WHERE slug = :slug', ['slug' => $outer]));
        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM organisations WHERE slug = :slug', ['slug' => $inner]));
    }

    private function insertOrganisation(string $slug): void
    {
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

        $this->connection()->insert('organisations', [
            'id' => Uuid::v7()->toRfc4122(),
            'name' => 'Transaction Test Organisation',
            'slug' => $slug,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function slug(string $prefix): string
    {
        return $prefix.'-'.bin2hex(random_bytes(8));
    }
}
