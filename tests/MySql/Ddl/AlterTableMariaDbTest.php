<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Ddl;

use IfCastle\AQL\MySql\Ddl\Parser\AlterTable;
use IfCastle\AQL\MySql\MariaDb;
use PHPUnit\Framework\TestCase;

/**
 * The SQL an ALTER TABLE statement renders to runs on MariaDB and leaves the indexes it names.
 */
final class AlterTableMariaDbTest extends TestCase
{
    private const string TABLE      = 'aql_alter_table_test';

    private \PDO $pdo;

    #[\Override]
    protected function setUp(): void
    {
        $this->pdo                  = MariaDb::pdo();
        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->pdo->exec(
            'CREATE TABLE ' . self::TABLE . ' (id INT NOT NULL, email VARCHAR(100) NOT NULL, title VARCHAR(100),'
            . ' body TEXT, place POINT NOT NULL, legacy INT) ENGINE=InnoDB'
        );
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    public function testRenderedIndexAdditionsRunAsOneStatement(): void
    {
        $statement                  = (new AlterTable())->parse(
            'ALTER TABLE ' . self::TABLE . ' ADD PRIMARY KEY (id), ADD INDEX idx_title (title),'
            . ' ADD UNIQUE u_email (email), ADD FULLTEXT ft_text (title, body), ADD SPATIAL INDEX sp_place (place),'
            . ' DROP COLUMN legacy'
        );

        $this->pdo->exec($statement->getResultAsString());

        $this->assertSame(
            [
                'PRIMARY'           => ['BTREE', '0', 'id'],
                'ft_text'           => ['FULLTEXT', '1', 'title,body'],
                'idx_title'         => ['BTREE', '1', 'title'],
                'sp_place'          => ['SPATIAL', '1', 'place'],
                'u_email'           => ['BTREE', '0', 'email'],
            ],
            $this->indexes()
        );
        $this->assertSame(0, (int) $this->pdo->query(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = \'' . self::TABLE . '\' AND COLUMN_NAME = \'legacy\''
        )->fetchColumn());
    }

    public function testRenderedOptionsAndRenamesRunAsOneStatement(): void
    {
        $renamed                    = self::TABLE . '_renamed';
        $this->pdo->exec('DROP TABLE IF EXISTS ' . $renamed);
        $this->pdo->exec('ALTER TABLE ' . self::TABLE . ' ADD KEY title_idx (title)');

        $statement                  = (new AlterTable())->parse(
            'ALTER TABLE ' . self::TABLE . ' ENGINE = InnoDB, COMMENT = \'Notes\' RENAME COLUMN email TO mail,'
            . ' RENAME INDEX title_idx TO heading_idx, ADD COLUMN (extra INT NOT NULL, note VARCHAR(10)),'
            . ' RENAME TO ' . $renamed
        );

        try {
            $this->pdo->exec($statement->getResultAsString());

            $created                = $this->pdo->query('SHOW CREATE TABLE ' . $renamed)->fetchColumn(1);
        } finally {
            $this->pdo->exec('DROP TABLE IF EXISTS ' . $renamed);
        }

        $this->assertStringContainsString('`mail` varchar(100) NOT NULL', $created);
        $this->assertStringContainsString('KEY `heading_idx` (`title`)', $created);
        $this->assertStringContainsString("COMMENT='Notes'", $created);
        $this->assertStringContainsString('`extra` int(11) NOT NULL', $created);
        $this->assertStringContainsString('`note` varchar(10) DEFAULT NULL', $created);
    }

    public function testRenderedKeyDropsRunAsOneStatement(): void
    {
        $this->pdo->exec('ALTER TABLE ' . self::TABLE . ' ADD PRIMARY KEY (id), ADD KEY idx_title (title)');

        $statement                  = (new AlterTable())->parse(
            'ALTER TABLE ' . self::TABLE . ' DROP PRIMARY KEY, DROP INDEX idx_title'
        );

        $this->pdo->exec($statement->getResultAsString());

        $this->assertSame([], $this->indexes());
    }

    /**
     * @return array<string, array{string, string, string}> index name => [type, non-unique flag, columns in order]
     */
    private function indexes(): array
    {
        $rows                       = $this->pdo->query(
            'SELECT INDEX_NAME, INDEX_TYPE, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)'
            . ' FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \''
            . self::TABLE . '\' GROUP BY INDEX_NAME, INDEX_TYPE, NON_UNIQUE'
        )->fetchAll(\PDO::FETCH_NUM);

        $indexes                    = [];

        foreach ($rows as [$name, $type, $nonUnique, $columns]) {
            $indexes[$name]         = [$type, (string) $nonUnique, $columns];
        }

        \ksort($indexes);

        return $indexes;
    }
}
