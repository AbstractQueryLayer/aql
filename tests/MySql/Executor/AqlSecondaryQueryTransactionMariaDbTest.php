<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Executor;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Executor\AqlExecutor;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\Executor\Entities\AqlRoundTripNote;
use IfCastle\AQL\Executor\Transaction\WithTransaction;
use IfCastle\AQL\MySql\MariaDb;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\TestCases\TestCaseWithDiContainer;
use IfCastle\AQL\Transaction\IsolationLevelEnum;
use IfCastle\DI\ContainerBuilder;

/**
 * A join across storages runs as a second query of the plan, on the connection of the other storage. Inside a
 * transaction that query belongs to the transaction too: under REPEATABLE READ it reads the snapshot.
 */
class AqlSecondaryQueryTransactionMariaDbTest extends TestCaseWithDiContainer
{
    private \PDO $pdo;

    private SqlRecordingMySql $main;

    private SqlRecordingMySql $remote;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo = MariaDb::pdo();
        $this->dropTables();
        $this->pdo->exec('CREATE TABLE aql_round_trip (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(64) NOT NULL)'
            . ' ENGINE=InnoDB');
        $this->pdo->exec('CREATE TABLE aql_round_trip_note (id INT AUTO_INCREMENT PRIMARY KEY, roundTripId INT NOT NULL,'
            . ' label VARCHAR(64) NOT NULL) ENGINE=InnoDB');
        $this->pdo->exec('INSERT INTO aql_round_trip (id, value) VALUES (1, "first"), (2, "second")');
        $this->pdo->exec('INSERT INTO aql_round_trip_note (roundTripId, label) VALUES (1, "note")');

        $this->main = $this->newStorage(StorageCollectionInterface::STORAGE_MAIN);
        $this->remote = $this->newStorage(AqlRoundTripNote::STORAGE);

        parent::setUp();
        $this->main->resolveDependencies($this->getDiContainer());
        $this->remote->resolveDependencies($this->getDiContainer());
    }

    #[\Override]
    protected function buildDiContainer(ContainerBuilder $containerBuilder): void
    {
        parent::buildDiContainer($containerBuilder);
        $containerBuilder->bindConstructible(AqlExecutorInterface::class, AqlExecutor::class, redefine: true);
        $containerBuilder->bindObject(
            StorageCollectionInterface::class,
            new StorageCollection([
                StorageCollectionInterface::STORAGE_MAIN => $this->main,
                AqlRoundTripNote::STORAGE                => $this->remote,
            ]),
            redefine: true,
        );
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->main->dispose();
        $this->remote->dispose();
        $this->dropTables();
    }

    public function testRelationReadInsideRepeatableReadSeesTheSnapshot(): void
    {
        $transaction = new WithTransaction($this->getAqlExecutor(), IsolationLevelEnum::REPEATABLE);

        [$first, $second] = $transaction->run(function (AqlExecutorInterface $executor): array {
            $first = $this->noteOwners($executor);
            $this->pdo->exec('UPDATE aql_round_trip_note SET roundTripId = 2');

            return [$first, $this->noteOwners($executor)];
        });

        $this->assertSame(['first'], $first);
        $this->assertSame(['first'], $second, 'The relation read saw a commit made after the snapshot');
        $this->assertStringContainsString('aql_round_trip_note', (string) $this->remote->lastSql);
        $this->assertSame(['second'], $this->noteOwners($this->getAqlExecutor()));
    }

    public function testRelationReadInsideReadCommittedSeesALaterCommit(): void
    {
        $transaction = new WithTransaction($this->getAqlExecutor(), IsolationLevelEnum::COMMITTED);

        $second = $transaction->run(function (AqlExecutorInterface $executor): array {
            $this->noteOwners($executor);
            $this->pdo->exec('UPDATE aql_round_trip_note SET roundTripId = 2');

            return $this->noteOwners($executor);
        });

        $this->assertSame(['second'], $second, 'The relation read did not run at the level of the transaction');
    }

    /**
     * Each storage is a connection of its own: its snapshot begins with its first read in the transaction, so a
     * commit made before that read is seen even though the other storage read earlier.
     */
    public function testAStorageFirstReadInsideTheTransactionTakesItsOwnSnapshot(): void
    {
        $transaction = new WithTransaction($this->getAqlExecutor(), IsolationLevelEnum::REPEATABLE);

        $owners = $transaction->run(function (AqlExecutorInterface $executor): array {
            $executor->executeAql((new AqlParser())->parse('SELECT value FROM AqlRoundTrip'))->finalize();
            $this->pdo->exec('UPDATE aql_round_trip_note SET roundTripId = 2');

            return $this->noteOwners($executor);
        });

        $this->assertSame(['second'], $owners);
    }

    public function testRelationReadInsideATransactionSeesItsOwnUncommittedChange(): void
    {
        $seen = [];

        try {
            (new WithTransaction($this->getAqlExecutor()))->run(function (AqlExecutorInterface $executor) use (&$seen): never {
                $executor->executeAql((new AqlParser())->parse('UPDATE AqlRoundTripNote SET roundTripId = 2 WHERE id = 1'));
                $seen = $this->noteOwners($executor);

                throw new \DomainException('roll back');
            });
        } catch (\DomainException) {
        }

        $this->assertSame(['second'], $seen);
        $this->assertSame(['first'], $this->noteOwners($this->getAqlExecutor()), 'The update was not rolled back');
    }

    /**
     * Values of the round trips that have a note: the notes are read by the second query of the plan.
     *
     * @return list<string>
     */
    private function noteOwners(AqlExecutorInterface $executor): array
    {
        $query = (new AqlParser())->parse('SELECT value FROM AqlRoundTrip INNER JOIN AqlRoundTripNote');

        return \array_column($executor->executeAql($query)->finalize()->toArray(), 'value');
    }

    private function newStorage(string $name): SqlRecordingMySql
    {
        $storage = new SqlRecordingMySql([
            'dsn' => MariaDb::dsn(),
            'username' => MariaDb::user(),
            'password' => MariaDb::password(),
            'options' => [\PDO::ATTR_POOL_MAX => 2],
        ]);
        $storage->setStorageName($name);

        return $storage;
    }

    private function dropTables(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip_note');
        $this->pdo->exec('DROP TABLE IF EXISTS aql_round_trip');
    }
}
