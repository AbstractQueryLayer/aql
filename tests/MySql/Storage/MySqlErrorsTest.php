<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Storage;

use Async\Channel;
use IfCastle\AQL\MySql\MariaDb;
use IfCastle\AQL\Storage\Exceptions\DuplicateKeysException;
use IfCastle\AQL\Storage\Exceptions\RecoverableException;
use IfCastle\AQL\Storage\Exceptions\ServerHasGoneAwayException;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use PHPUnit\Framework\TestCase;

use function Async\await;
use function Async\delay;
use function Async\spawn;

class MySqlErrorsTest extends TestCase
{
    private const string TABLE      = 'mysql_errors_test';

    // Rows the deadlock's other side changes, so that InnoDB rolls back the lighter statement under test.
    private const int HEAVY_ROWS    = 100;

    // Milliseconds for the statement under test to take its first lock and start waiting for the second.
    private const int LOCK_WAIT_MS  = 200;

    private \PDO $pdo;

    private MySql $mySql;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo                  = MariaDb::pdo();
        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->pdo->exec('CREATE TABLE ' . self::TABLE . ' (id INT PRIMARY KEY, counter INT NOT NULL) ENGINE=InnoDB');
        $this->pdo->exec(
            'INSERT INTO ' . self::TABLE . ' (id, counter) SELECT seq, 0 FROM seq_1_to_' . self::HEAVY_ROWS
        );

        $this->mySql                = new MySql([
            'dsn'                   => MariaDb::dsn(),
            'username'              => MariaDb::user(),
            'password'              => MariaDb::password(),
        ]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->mySql->dispose();
        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    public function testDuplicateKeyRaisesDuplicateKeysException(): void
    {
        $exception                  = $this->failureOf('INSERT INTO ' . self::TABLE . ' (id, counter) VALUES (1, 0)');

        $this->assertInstanceOf(DuplicateKeysException::class, $exception);
        $this->assertStringContainsString('Duplicate entry', $exception->getMessage());
    }

    public function testOtherErrorsAreNotRecoverable(): void
    {
        $exception                  = $this->failureOf('SELECT no_such_column FROM ' . self::TABLE);

        $this->assertNotInstanceOf(RecoverableException::class, $exception);
        $this->assertNotInstanceOf(DuplicateKeysException::class, $exception);
    }

    public function testDeadlockRaisesRecoverableException(): void
    {
        // The other side locks row 1, then asks for row 2, which the statement under test holds while
        // it waits for row 1. Its HEAVY_ROWS changes make the statement under test the deadlock victim.
        $this->pdo->beginTransaction();
        $this->pdo->exec('UPDATE ' . self::TABLE . ' SET counter = counter + 1 WHERE id > 2');
        $this->pdo->exec('UPDATE ' . self::TABLE . ' SET counter = counter + 1 WHERE id = 1');

        $victim                     = spawn(fn() => $this->failureOf(
            'UPDATE ' . self::TABLE . ' SET counter = counter + 1 WHERE id IN (1, 2) ORDER BY id DESC'
        ));

        delay(self::LOCK_WAIT_MS);
        $this->pdo->exec('UPDATE ' . self::TABLE . ' SET counter = counter + 1 WHERE id = 2');
        $this->pdo->rollBack();

        $exception                  = await($victim);

        $this->assertInstanceOf(RecoverableException::class, $exception);
        $this->assertNotInstanceOf(ServerHasGoneAwayException::class, $exception);
    }

    public function testLostConnectionLeavesOtherCoroutinesConnectionsOpen(): void
    {
        $killed                     = new Channel(1);

        // The holder keeps its connection pinned by an open result and marks its session.
        $holder                     = spawn(function () use ($killed): array {
            $pinned                 = $this->mySql->executeSql('SELECT CONNECTION_ID() AS connection');
            $this->mySql->executeSql('SET @mark = 1');
            $killed->recv();

            $after                  = $this->mySql->executeSql('SELECT CONNECTION_ID() AS connection, @mark AS mark');

            return [$pinned->toArray()[0]['connection'], $after->toArray()[0]];
        });

        $exception                  = await(spawn(function (): ?StorageException {
            $connection             = $this->mySql->executeSql('SELECT CONNECTION_ID() AS connection')->toArray()[0]['connection'];
            $this->pdo->exec('KILL ' . (int) $connection);

            return $this->failureOf('SELECT 1');
        }));

        $killed->send(true);
        [$before, $after]           = await($holder);

        $this->assertInstanceOf(ServerHasGoneAwayException::class, $exception);
        $this->assertSame([$before, 1], [$after['connection'], (int) $after['mark']], 'the holder kept its connection');
        $this->assertSame([['one' => 1]], await(spawn(fn() => $this->mySql->executeSql('SELECT 1 AS one')->toArray())));
    }

    private function failureOf(string $sql): ?StorageException
    {
        try {
            $this->mySql->executeSql($sql);
        } catch (StorageException $exception) {
            return $exception;
        }

        return null;
    }
}
