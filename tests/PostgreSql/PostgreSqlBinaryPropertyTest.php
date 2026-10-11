<?php

declare(strict_types=1);

namespace IfCastle\AQL\PostgreSql;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Executor\AqlExecutor;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\PostgreSql\Storage\PostgreSql;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\TestCases\TestCaseWithDiContainer;
use IfCastle\DI\ContainerBuilder;

/** PropertyBinary on a real PostgreSQL bytea column (the AqlBinaryHash entity, table aql_binary_hash). */
final class PostgreSqlBinaryPropertyTest extends TestCaseWithDiContainer
{
    // A NUL byte, 0xFF that never occurs in UTF-8, a quote and a backslash.
    private const string HASH       = "\x00\xff'\\" . "\xc3\xc3\xc3\xc3\xc3\xc3\xc3\xc3\xc3\xc3\xc3\xc3\xc3\xc3"
        . "\xc3\xc3\xc3\xc3\xc3\xc3\xc3\xc3\xc3\xc3\xc3\xc3\xc3\xc3";

    private \PDO $pdo;

    private PostgreSql $storage;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo                  = PostgresServer::pdo();
        $this->pdo->exec('DROP TABLE IF EXISTS aql_binary_hash');
        $this->pdo->exec('CREATE TABLE aql_binary_hash (id SERIAL PRIMARY KEY, hash BYTEA NOT NULL)');
        $this->storage              = new PostgreSql([
            'dsn'                   => PostgresServer::dsn(),
            'username'              => PostgresServer::user(),
            'password'              => PostgresServer::password(),
        ]);
        $this->storage->setStorageName(StorageCollectionInterface::STORAGE_MAIN);

        parent::setUp();
        $this->storage->resolveDependencies($this->getDiContainer());
    }

    #[\Override]
    protected function buildDiContainer(ContainerBuilder $containerBuilder): void
    {
        parent::buildDiContainer($containerBuilder);
        $containerBuilder->bindConstructible(AqlExecutorInterface::class, AqlExecutor::class, redefine: true);
        $containerBuilder->bindObject(
            StorageCollectionInterface::class,
            new StorageCollection([StorageCollectionInterface::STORAGE_MAIN => $this->storage]),
            redefine: true,
        );
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->storage->disconnect();
        $this->pdo->exec('DROP TABLE IF EXISTS aql_binary_hash');
    }

    /** Why the type exists: as text, a value is cut at a NUL byte without an error, or cannot be quoted at all. */
    public function testTextQuotingLosesTheBytes(): void
    {
        $this->pdo->exec('INSERT INTO aql_binary_hash (hash) VALUES (' . $this->pdo->quote(self::HASH) . ')');
        $stored                     = $this->pdo->query('SELECT encode(hash, \'hex\') FROM aql_binary_hash')->fetchColumn();

        $this->assertSame('', $stored);
        $this->assertFalse($this->pdo->quote(\substr(self::HASH, 1)));
    }

    public function testBinaryValuesRoundTripByteExact(): void
    {
        $other                      = \str_repeat("\x00", 31) . "\xff";
        $executor                   = $this->getAqlExecutor();

        // INSERT … SET and UPDATE … SET render MySQL syntax on PostgreSQL (lib plan S28.1), so only VALUES writes here.
        $insert                     = (new AqlParser())->parse('INSERT INTO AqlBinaryHash (hash) VALUES ({first}), ({second})');
        $insert->applyParameters(['first' => self::HASH, 'second' => $other]);
        $this->assertSame(2, $executor->executeAql($insert)->getAffectedRows());

        $stored                     = $this->pdo->query('SELECT encode(hash, \'hex\') FROM aql_binary_hash ORDER BY id')
            ->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['00ff275c' . \str_repeat('c3', 28), \str_repeat('00', 31) . 'ff'], $stored);

        $select                     = (new AqlParser())->parse('SELECT hash FROM AqlBinaryHash WHERE hash = {hash}');
        $select->applyParameters(['hash' => self::HASH]);
        $this->assertSame([['hash' => self::HASH]], $executor->executeAql($select)->finalize()->toArray());

        $list                       = (new AqlParser())->parse(
            'SELECT hash FROM AqlBinaryHash WHERE hash IN ({first}, {second}) ORDER BY id'
        );
        $list->applyParameters(['first' => \str_repeat("\xff", 32), 'second' => $other]);
        $this->assertSame([['hash' => $other]], $executor->executeAql($list)->finalize()->toArray());
    }
}
