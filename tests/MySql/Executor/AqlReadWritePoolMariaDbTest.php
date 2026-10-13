<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Executor;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Executor\AqlExecutor;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\Executor\Transaction\WithTransaction;
use IfCastle\AQL\MySql\MariaDb;
use IfCastle\AQL\StoragePool\ReadWritePool\SqlReadWritePool;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\TestCases\TestCaseWithDiContainer;
use IfCastle\DI\ContainerBuilder;

/**
 * AQL through SqlReadWritePool on MariaDB, the reader and the writer being separate connections: a read inside a
 * transaction must see the transaction's own uncommitted row, which only the writer's connection can see.
 */
class AqlReadWritePoolMariaDbTest extends TestCaseWithDiContainer
{
    private const string TABLE = 'aql_round_trip';

    private \PDO $pdo;

    private SqlRecordingMySql $reader;

    private SqlRecordingMySql $writer;

    private SqlReadWritePool $pool;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo = MariaDb::pdo();
        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->pdo->exec('CREATE TABLE ' . self::TABLE
            . ' (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(64) NOT NULL) ENGINE=InnoDB');
        $this->reader = $this->newStorage();
        $this->writer = $this->newStorage();
        $this->pool = new SqlReadWritePool(fn() => $this->reader, fn() => $this->writer);
        $this->pool->setStorageName(StorageCollectionInterface::STORAGE_MAIN);

        parent::setUp();
        $this->reader->resolveDependencies($this->getDiContainer());
        $this->writer->resolveDependencies($this->getDiContainer());
    }

    #[\Override]
    protected function buildDiContainer(ContainerBuilder $containerBuilder): void
    {
        parent::buildDiContainer($containerBuilder);
        $containerBuilder->bindConstructible(AqlExecutorInterface::class, AqlExecutor::class, redefine: true);
        $containerBuilder->bindObject(
            StorageCollectionInterface::class,
            new StorageCollection([StorageCollectionInterface::STORAGE_MAIN => $this->pool]),
            redefine: true,
        );
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->reader->dispose();
        $this->writer->dispose();
        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    public function testReadInsideATransactionSeesItsOwnUncommittedRow(): void
    {
        $seen = (new WithTransaction($this->getAqlExecutor()))->run(function (AqlExecutorInterface $executor): array {
            $executor->executeAql((new AqlParser())->parse('INSERT INTO AqlRoundTrip SET value = "uncommitted"'));

            return $this->values($executor);
        });

        $this->assertSame(['uncommitted'], $seen);
        $this->assertNull($this->reader->lastSql, 'A read inside the transaction reached the reader');
    }

    public function testReadOutsideATransactionGoesToTheReader(): void
    {
        $this->pdo->exec('INSERT INTO ' . self::TABLE . ' (value) VALUES ("committed")');
        // The pool sends a statement to the writer until the writer exists; create it first.
        $this->getAqlExecutor()->executeAql((new AqlParser())->parse('DELETE FROM AqlRoundTrip WHERE id = 0'));
        $this->writer->lastSql = null;

        $this->assertSame(['committed'], $this->values($this->getAqlExecutor()));
        $this->assertNotNull($this->reader->lastSql);
        $this->assertNull($this->writer->lastSql);
    }

    private function newStorage(): SqlRecordingMySql
    {
        return new SqlRecordingMySql([
            'dsn' => MariaDb::dsn(),
            'username' => MariaDb::user(),
            'password' => MariaDb::password(),
            'options' => [\PDO::ATTR_POOL_MAX => 2],
        ]);
    }

    /** @return list<string> */
    private function values(AqlExecutorInterface $executor): array
    {
        $rows = $executor->executeAql((new AqlParser())->parse('SELECT value FROM AqlRoundTrip'))->finalize()->toArray();

        return \array_column($rows, 'value');
    }
}
