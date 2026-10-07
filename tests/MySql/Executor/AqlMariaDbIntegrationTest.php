<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Executor;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperation;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperationInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where;
use IfCastle\AQL\Dsl\Sql\Query\Subquery;
use IfCastle\AQL\Executor\AqlExecutor;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\Executor\Transaction\WithCompensatingTransaction;
use IfCastle\AQL\Executor\Transaction\WithTransaction;
use IfCastle\AQL\MySql\MariaDb;
use IfCastle\AQL\MySql\Storage\MySql;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\TestCases\TestCaseWithDiContainer;
use IfCastle\DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

use function Async\await;
use function Async\delay;
use function Async\spawn;

/** Parses and executes AQL on a real MariaDB table through one shared executor and PDO pool. */
class AqlMariaDbIntegrationTest extends TestCaseWithDiContainer
{
    private const string TABLE = 'aql_round_trip';

    private \PDO $pdo;

    private MySql $mySql;

    private AqlExecutorInterface $aqlExecutor;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo = MariaDb::pdo();
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_link');
        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->pdo->exec('CREATE TABLE ' . self::TABLE . ' (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(64) NOT NULL) ENGINE=InnoDB');
        $this->pdo->exec('CREATE TABLE aql_round_trip_link (id INT AUTO_INCREMENT PRIMARY KEY, roundTripId INT NOT NULL, label VARCHAR(64) NOT NULL) ENGINE=InnoDB');
        $this->mySql = new MySql([
            'dsn' => MariaDb::dsn(),
            'username' => MariaDb::user(),
            'password' => MariaDb::password(),
            'options' => [\PDO::ATTR_POOL_MAX => 10],
        ]);
        $this->mySql->setStorageName(StorageCollectionInterface::STORAGE_MAIN);

        parent::setUp();
        $this->mySql->resolveDependencies($this->getDiContainer());
        $this->aqlExecutor = $this->getAqlExecutor();
    }

    #[\Override]
    protected function buildDiContainer(ContainerBuilder $containerBuilder): void
    {
        parent::buildDiContainer($containerBuilder);
        $containerBuilder->bindConstructible(AqlExecutorInterface::class, AqlExecutor::class, redefine: true);
        $containerBuilder->bindObject(
            StorageCollectionInterface::class,
            new StorageCollection([StorageCollectionInterface::STORAGE_MAIN => $this->mySql]),
            redefine: true,
        );
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->mySql->dispose();
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_link');
        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    public function testAqlInsertAndSelectReachMariaDb(): void
    {
        $id = $this->insert($this->aqlExecutor, 1);
        $this->assertGreaterThan(0, $id);
        $this->assertSame([['id' => $id, 'value' => 'worker-1']], $this->select($this->aqlExecutor, $id));
    }

    public function testAggregateFunctionArgumentsReachMariaDb(): void
    {
        $ids = [
            $this->insert($this->aqlExecutor, 1),
            $this->insert($this->aqlExecutor, 2),
            $this->insert($this->aqlExecutor, 3),
        ];
        $query = (new AqlParser())->parse(
            'SELECT COUNT(id) AS total, SUM(id) AS idSum FROM AqlRoundTrip'
        );
        $rows = $this->aqlExecutor->executeAql($query)->finalize()->toArray();

        $this->assertCount(1, $rows);
        $this->assertSame(3, (int) $rows[0]['total']);
        $this->assertSame(\array_sum($ids), (int) $rows[0]['idSum']);
    }

    public function testDescendingOrderIsAppliedByMariaDb(): void
    {
        $ids = [
            $this->insert($this->aqlExecutor, 1),
            $this->insert($this->aqlExecutor, 2),
            $this->insert($this->aqlExecutor, 3),
        ];
        $query = (new AqlParser())->parse('SELECT id, value FROM AqlRoundTrip ORDER BY id DESC');
        $rows = $this->aqlExecutor->executeAql($query)->finalize()->toArray();

        $this->assertSame(\array_reverse($ids), \array_map('intval', \array_column($rows, 'id')));
    }

    public function testIndependentInSubqueryNeedsNoEntityRelation(): void
    {
        $first = $this->insert($this->aqlExecutor, 1);
        $second = $this->insert($this->aqlExecutor, 2);
        $this->pdo->exec('INSERT INTO aql_round_trip_link (roundTripId, label) VALUES (' . $second . ', "match")');

        $subquery = new Subquery('AqlRoundTripLink', ['roundTripId'],
            (new Where())->equal('label', new Constant('match')));
        $query = (new AqlParser())->parse('SELECT id, value FROM AqlRoundTrip');
        $query->setWhere((new Where())->add(new LROperation('id', LROperationInterface::IN, $subquery)));
        $rows = $this->aqlExecutor->executeAql($query)->finalize()->toArray();

        $this->assertSame([['id' => $second, 'value' => 'worker-2']], $rows);
        $this->assertNotSame($first, $second);
    }

    public function testTenOverlappingCoroutinesInsertAndReadTheirOwnRows(): void
    {
        $workers = [];

        for ($worker = 1; $worker <= 10; $worker++) {
            $workers[] = spawn(function () use ($worker): array {
                $id = $this->insert($this->aqlExecutor, $worker);
                delay(20);

                return [$id, $this->select($this->aqlExecutor, $id)];
            });
        }

        $ids = [];
        foreach ($this->awaitAll($workers) as $index => [$id, $rows]) {
            $this->assertGreaterThan(0, $id);
            $this->assertSame([['id' => $id, 'value' => 'worker-' . ($index + 1)]], $rows);
            $ids[] = $id;
        }

        $this->assertCount(10, \array_unique($ids));
        $this->assertCount(10, $this->pdo->query('SELECT id FROM ' . self::TABLE)->fetchAll());
    }

    public static function transactionWrappers(): array
    {
        return [
            'ordinary' => [WithTransaction::class],
            'compensating' => [WithCompensatingTransaction::class],
        ];
    }

    #[DataProvider('transactionWrappers')]
    public function testWithTransactionCommitsAndRollsBackRealAqlQueries(string $wrapperClass): void
    {
        $transactional = new $wrapperClass($this->aqlExecutor);
        $committedId = $transactional->run(function (AqlExecutorInterface $executor): int {
            $id = $this->insert($executor, 101);
            $this->assertSame([['id' => $id, 'value' => 'worker-101']], $this->select($executor, $id));
            return $id;
        });

        $rolledBackId = null;
        try {
            $transactional->run(function (AqlExecutorInterface $executor) use (&$rolledBackId): never {
                $rolledBackId = $this->insert($executor, 102);
                $this->assertSame([['id' => $rolledBackId, 'value' => 'worker-102']], $this->select($executor, $rolledBackId));
                throw new \RuntimeException('roll back this row');
            });
            $this->fail('The transaction callback did not throw');
        } catch (\RuntimeException $exception) {
            $this->assertSame('roll back this row', $exception->getMessage());
        }

        $this->assertSame([['id' => $committedId, 'value' => 'worker-101']], $this->select($this->aqlExecutor, $committedId));
        $this->assertNotNull($rolledBackId);
        $this->assertSame([], $this->select($this->aqlExecutor, $rolledBackId));
    }

    #[DataProvider('transactionWrappers')]
    public function testNestedTransactionsKeepOuterChangesAfterChildRollback(string $wrapperClass): void
    {
        $transactional = new $wrapperClass($this->aqlExecutor);
        $outerId = null;
        $childId = null;
        $afterChildId = null;

        $transactional->run(function (WithTransaction|WithCompensatingTransaction $outer) use (&$outerId, &$childId, &$afterChildId): void {
            $outerId = $this->insert($outer, 201);

            try {
                $outer->run(function (AqlExecutorInterface $child) use (&$childId): never {
                    $childId = $this->insert($child, 202);
                    throw new \RuntimeException('roll back child');
                });
                $this->fail('The child callback did not throw');
            } catch (\RuntimeException $exception) {
                $this->assertSame('roll back child', $exception->getMessage());
            }

            $afterChildId = $this->insert($outer, 203);
        });

        $this->assertNotNull($outerId);
        $this->assertNotNull($childId);
        $this->assertNotNull($afterChildId);
        $this->assertSame([['id' => $outerId, 'value' => 'worker-201']], $this->select($this->aqlExecutor, $outerId));
        $this->assertSame([], $this->select($this->aqlExecutor, $childId));
        $this->assertSame([['id' => $afterChildId, 'value' => 'worker-203']], $this->select($this->aqlExecutor, $afterChildId));
    }

    #[DataProvider('transactionWrappers')]
    public function testOuterRollbackUndoesCommittedChild(string $wrapperClass): void
    {
        $transactional = new $wrapperClass($this->aqlExecutor);
        $outerId = null;
        $childId = null;

        try {
            $transactional->run(function (WithTransaction|WithCompensatingTransaction $outer) use (&$outerId, &$childId): never {
                $outerId = $this->insert($outer, 301);
                $outer->run(function (AqlExecutorInterface $child) use (&$childId): void {
                    $childId = $this->insert($child, 302);
                });
                throw new \RuntimeException('roll back outer');
            });
            $this->fail('The outer callback did not throw');
        } catch (\RuntimeException $exception) {
            $this->assertSame('roll back outer', $exception->getMessage());
        }

        $this->assertNotNull($outerId);
        $this->assertNotNull($childId);
        $this->assertSame([], $this->select($this->aqlExecutor, $outerId));
        $this->assertSame([], $this->select($this->aqlExecutor, $childId));
    }

    #[DataProvider('transactionWrappers')]
    public function testConcurrentRunsOnOneWrapperKeepTransactionsIndependent(string $wrapperClass): void
    {
        $transactional = new $wrapperClass($this->aqlExecutor);
        $workers = [];

        for ($worker = 401; $worker <= 410; $worker++) {
            $workers[] = spawn(function () use ($transactional, $worker): array {
                return $transactional->run(function (AqlExecutorInterface $executor) use ($worker): array {
                    $id = $this->insert($executor, $worker);
                    delay(20);

                    return [$id, $this->select($executor, $id)];
                });
            });
        }

        $ids = [];
        foreach ($this->awaitAll($workers) as $index => [$id, $rows]) {
            $this->assertGreaterThan(0, $id);
            $this->assertSame([['id' => $id, 'value' => 'worker-' . (401 + $index)]], $rows);
            $ids[] = $id;
        }

        $this->assertCount(10, \array_unique($ids));
        $this->assertCount(10, $this->pdo->query('SELECT id FROM ' . self::TABLE)->fetchAll());
    }

    #[DataProvider('transactionWrappers')]
    public function testConcurrentCommitChildRollbackAndThrowAreIndependent(string $wrapperClass): void
    {
        $transactional = new $wrapperClass($this->aqlExecutor);
        $workers = [];

        for ($worker = 1; $worker <= 10; $worker++) {
            $workers[] = spawn(function () use ($transactional, $worker): array {
                return match ($worker % 3) {
                    1 => $transactional->run(function (AqlExecutorInterface $executor) use ($worker): array {
                        $id = $this->insert($executor, $worker);
                        delay(20);
                        $this->assertSame([['id' => $id, 'value' => 'worker-' . $worker]], $this->select($executor, $id));
                        return ['commit', $id];
                    }),
                    2 => $transactional->run(function (WithTransaction|WithCompensatingTransaction $outer) use ($worker): array {
                        $id = null;
                        try {
                            $outer->run(function (AqlExecutorInterface $child) use ($worker, &$id): never {
                                $id = $this->insert($child, $worker);
                                delay(20);
                                throw new \RuntimeException('roll back child ' . $worker);
                            });
                            $this->fail('The child callback did not throw');
                        } catch (\RuntimeException $exception) {
                            $this->assertSame('roll back child ' . $worker, $exception->getMessage());
                        }
                        return ['rollback', $id];
                    }),
                    default => $this->runThrowingWorker($transactional, $worker),
                };
            });
        }

        $ids = [];
        $committed = [];
        foreach ($this->awaitAll($workers) as $index => [$outcome, $id]) {
            $this->assertIsInt($id);
            $this->assertGreaterThan(0, $id);
            $ids[] = $id;
            if ($outcome === 'commit') {
                $committed[] = ['id' => $id, 'value' => 'worker-' . ($index + 1)];
            }
        }

        $this->assertCount(10, \array_unique($ids));
        \usort($committed, static fn(array $left, array $right): int => $left['id'] <=> $right['id']);
        $actual = await(spawn(function (): array {
            $query = (new AqlParser())->parse('SELECT id, value FROM AqlRoundTrip ORDER BY id');
            return $this->aqlExecutor->executeAql($query)->finalize()->toArray();
        }));
        $this->assertSame($committed, $actual);
    }

    private function runThrowingWorker(
        WithTransaction|WithCompensatingTransaction $transactional,
        int $worker,
    ): array {
        $id = null;
        try {
            $transactional->run(function (AqlExecutorInterface $executor) use ($worker, &$id): never {
                $id = $this->insert($executor, $worker);
                delay(20);
                throw new \RuntimeException('fail outer ' . $worker);
            });
            $this->fail('The outer callback did not throw');
        } catch (\RuntimeException $exception) {
            $this->assertSame('fail outer ' . $worker, $exception->getMessage());
        }

        return ['throw', $id];
    }

    private function insert(AqlExecutorInterface $executor, int $worker): int
    {
        $query = (new AqlParser())->parse('INSERT INTO AqlRoundTrip SET value = "worker-' . $worker . '"');
        $result = $executor->executeAql($query);
        $this->assertSame(1, $result->getAffectedRows());
        $key = $result->getLastPrimaryKey();

        if (!\is_array($key) || !isset($key['id'])) {
            throw new \LogicException('The AQL insert did not return its generated id');
        }

        return (int) $key['id'];
    }

    private function select(AqlExecutorInterface $executor, int $id): array
    {
        $query = (new AqlParser())->parse('SELECT id, value FROM AqlRoundTrip WHERE id = ' . $id);

        return $executor->executeAql($query)->finalize()->toArray();
    }

    /** @param list<\Async\Coroutine> $workers */
    private function awaitAll(array $workers): array
    {
        $results = [];
        $firstError = null;

        foreach ($workers as $index => $worker) {
            try {
                $results[$index] = await($worker);
            } catch (\Throwable $exception) {
                $firstError ??= $exception;
            }
        }

        if ($firstError !== null) {
            throw $firstError;
        }

        return $results;
    }
}
