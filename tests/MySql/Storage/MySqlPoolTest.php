<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Storage;

use IfCastle\AQL\MySql\MariaDb;
use IfCastle\DI\Exceptions\ConfigException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Async\await;
use function Async\spawn;

class MySqlPoolTest extends TestCase
{
    private const int COROUTINES    = 20;

    private const int POOL_MAX      = 5;

    // Seconds each query holds its connection.
    private const float QUERY_SECONDS = 0.2;

    private const string TIME_ZONE  = '+03:00';

    public function testCoroutinesShareABoundedPoolAndEachGetsTheSessionSetup(): void
    {
        $mySql                      = $this->mySql([
            'options'               => [\PDO::ATTR_POOL_MAX => self::POOL_MAX],
            'initial_queries'       => "SET time_zone = '" . self::TIME_ZONE . "'",
        ]);

        $sql                        = 'SELECT CONNECTION_ID() AS connection, @@session.time_zone AS timeZone, SLEEP('
                                    . self::QUERY_SECONDS . ') AS slept';

        $started                    = \microtime(true);
        $coroutines                 = [];

        for ($i = 0; $i < self::COROUTINES; $i++) {
            $coroutines[]           = spawn(static fn() => $mySql->executeSql($sql)->toArray()[0]);
        }

        $rows                       = \array_map(static fn($coroutine) => await($coroutine), $coroutines);
        $elapsed                    = \microtime(true) - $started;

        $this->assertCount(self::POOL_MAX, \array_unique(\array_column($rows, 'connection')));
        $this->assertLessThanOrEqual(self::POOL_MAX, $this->serverConnections(), 'connections the server sees');
        $this->assertSame(\array_fill(0, self::COROUTINES, self::TIME_ZONE), \array_column($rows, 'timeZone'));

        // COROUTINES / POOL_MAX rounds of QUERY_SECONDS each: serial execution would take 4 s.
        $rounds                     = self::COROUTINES / self::POOL_MAX;
        $this->assertGreaterThanOrEqual($rounds * self::QUERY_SECONDS, $elapsed);
        $this->assertLessThan($rounds * self::QUERY_SECONDS + 0.7, $elapsed);

        $mySql->dispose();
    }

    public function testAResetConnectionGetsTheSessionSetupAgain(): void
    {
        $mySql                      = $this->mySql([
            'options'               => [\PDO::ATTR_POOL_MAX => 1],
            'initial_queries'       => "SET time_zone = '" . self::TIME_ZONE . "'",
        ]);

        $changed                    = await(spawn(static function () use ($mySql): array {
            $mySql->executeSql("SET time_zone = '+00:00'");

            return $mySql->executeSql('SELECT CONNECTION_ID() AS connection, @@session.time_zone AS timeZone')->toArray()[0];
        }));

        $next                       = await(spawn(
            static fn() => $mySql->executeSql('SELECT CONNECTION_ID() AS connection, @@session.time_zone AS timeZone')->toArray()[0]
        ));

        $this->assertSame('+00:00', $changed['timeZone']);
        $this->assertSame([$changed['connection'], self::TIME_ZONE], [$next['connection'], $next['timeZone']]);

        $mySql->dispose();
    }

    /**
     * @param array<int|string, mixed> $options
     */
    #[DataProvider('unusableOptions')]
    public function testOptionsThatBreakThePoolAreRefused(array $options, string $initialQueries = ''): void
    {
        $this->expectException(ConfigException::class);

        $this->mySql(['options' => $options, 'initial_queries' => $initialQueries]);
    }

    /**
     * @return array<string, array{0: array<int|string, mixed>, 1?: string}>
     */
    public static function unusableOptions(): array
    {
        return [
            'persistent'            => [[\PDO::ATTR_PERSISTENT => true]],
            'pool switched off'     => [[\PDO::ATTR_POOL_ENABLED => false]],
            'option named by string' => [['PDO::ATTR_POOL_MAX' => 5]],
            'two initial queries'   => [[], "SET NAMES utf8mb4; SET time_zone = '+03:00'"],
            'both init commands'    => [[\Pdo\Mysql::ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'], 'SET NAMES utf8mb4'],
        ];
    }

    /**
     * Connections of the test user to the test database, counted by the server, not by the pool.
     */
    private function serverConnections(): int
    {
        $pdo                        = MariaDb::pdo();
        $statement                  = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE USER = ? AND DB = ? AND ID <> CONNECTION_ID()'
        );
        $statement->execute([MariaDb::user(), MariaDb::database()]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array<string, mixed> $config merged over the test server's connection settings
     */
    private function mySql(array $config): MySql
    {
        return new MySql($config + [
            'dsn'                   => MariaDb::dsn(),
            'username'              => MariaDb::user(),
            'password'              => MariaDb::password(),
        ]);
    }
}
