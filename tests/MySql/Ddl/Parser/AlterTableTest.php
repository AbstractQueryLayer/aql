<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Ddl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Parser\SqlString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AlterTableTest extends TestCase
{
    protected function matchCase(string $inputSql, string $expectedAql): void
    {
        $table                      = (new AlterTable())->parse($inputSql);

        $this->assertEquals($expectedAql, SqlString::trim($table->getAql()));
    }

    public function testSimple(): void
    {
        $this->matchCase(
            /** @lang aql */
            <<<'SQL'
                ALTER TABLE `users`
                  CHANGE COLUMN `email_verified_at` `email_verified_at` timestamp NULL DEFAULT NULL AFTER `name`,
                  CHANGE COLUMN `password` `password` varchar(200) NOT NULL DEFAULT '' AFTER `email_verified_at`,
                  CHANGE COLUMN `remember_token` `remember_token` varchar(100) NOT NULL DEFAULT '2',
                  ADD COLUMN `new_property` varchar(255) NULL DEFAULT NULL COMMENT 'New property comment';
                SQL
            ,
            /** @lang aql */
            <<<'SQL'
                ALTER TABLE `users`
                CHANGE COLUMN `email_verified_at` `email_verified_at` TIMESTAMP NULL DEFAULT NULL AFTER `name`,
                CHANGE COLUMN `password` `password` VARCHAR (200) NOT NULL DEFAULT '' AFTER `email_verified_at`,
                CHANGE COLUMN `remember_token` `remember_token` VARCHAR (100) NOT NULL DEFAULT '2',
                ADD COLUMN `new_property` VARCHAR (255) NULL DEFAULT NULL COMMENT 'New property comment'
                SQL
        );
    }

    public function testPartitionByHash(): void
    {
        $this->matchCase(
            /** @lang aql */
            <<<'SQL'
                ALTER TABLE `users`
                PARTITION BY HASH(id)
                PARTITIONS 8
                SQL
            ,
            /** @lang aql */
            <<<'SQL'
                ALTER TABLE `users`
                PARTITION BY HASH (id) PARTITIONS 8
                SQL
        );
    }

    public function testPartitionByLinearKey(): void
    {
        $this->matchCase(
            /** @lang aql */
            <<<'SQL'
                ALTER TABLE `users`
                PARTITION BY LINEAR KEY ALGORITHM = 2 (id, email)
                PARTITIONS 4
                SQL
            ,
            /** @lang aql */
            <<<'SQL'
                ALTER TABLE `users`
                PARTITION BY LINEAR KEY ALGORITHM = 2 (`id`, `email`) PARTITIONS 4
                SQL
        );
    }

    public function testPartitionByRange(): void
    {
        $this->matchCase(
            /** @lang aql */
            <<<'SQL'
                ALTER TABLE `users`
                PARTITION BY RANGE (year_col) (
                    PARTITION p0 VALUES LESS THAN (1991),
                    PARTITION p1 VALUES LESS THAN (1995),
                    PARTITION p2 VALUES LESS THAN (1999)
                )
                SQL
            ,
            /** @lang aql */
            <<<'SQL'
                ALTER TABLE `users`
                PARTITION BY RANGE (year_col) (
                PARTITION `p0` VALUES LESS THAN (1991),
                PARTITION `p1` VALUES LESS THAN (1995),
                PARTITION `p2` VALUES LESS THAN (1999)
                )
                SQL
        );
    }

    public function testPartitionByRangeColumns(): void
    {
        $this->matchCase(
            /** @lang aql */
            <<<'SQL'
                ALTER TABLE `users`
                PARTITION BY RANGE COLUMNS (year_col, month_col) (
                    PARTITION p0 VALUES LESS THAN (1991, 1),
                    PARTITION p1 VALUES LESS THAN (1995, 12)
                )
                SQL
            ,
            /** @lang aql */
            <<<'SQL'
                ALTER TABLE `users`
                PARTITION BY RANGE COLUMNS (`year_col`, `month_col`) (
                PARTITION `p0` VALUES LESS THAN (1991, 1),
                PARTITION `p1` VALUES LESS THAN (1995, 12)
                )
                SQL
        );
    }

    public function testPartitionByList(): void
    {
        $this->matchCase(
            /** @lang aql */
            <<<'SQL'
                ALTER TABLE `users`
                PARTITION BY LIST (status) (
                    PARTITION p0 VALUES IN (1, 2, 3),
                    PARTITION p1 VALUES IN (4, 5, 6)
                )
                SQL
            ,
            /** @lang aql */
            <<<'SQL'
                ALTER TABLE `users`
                PARTITION BY LIST (status) (
                PARTITION `p0` VALUES IN (1, 2, 3),
                PARTITION `p1` VALUES IN (4, 5, 6)
                )
                SQL
        );
    }

    /** @return iterable<string, array{string, string}> */
    public static function indexAdditions(): iterable
    {
        yield 'index' => ['ADD INDEX idx_email (email)', 'ADD KEY `idx_email` (`email`)'];
        yield 'key' => ['ADD KEY `idx_email` (`email`, `name`)', 'ADD KEY `idx_email` (`email`,`name`)'];
        yield 'unnamed index' => ['ADD INDEX (email)', 'ADD KEY (`email`)'];
        yield 'primary key' => ['ADD PRIMARY KEY (id)', 'ADD PRIMARY KEY (`id`)'];
        yield 'unique index' => ['ADD UNIQUE INDEX u_email (email)', 'ADD UNIQUE KEY `u_email` (`email`)'];
        yield 'unique without index' => ['ADD UNIQUE u_email (email)', 'ADD UNIQUE KEY `u_email` (`email`)'];
        yield 'fulltext index' => ['ADD FULLTEXT INDEX ft_text (title, body)', 'ADD FULLTEXT KEY `ft_text` (`title`,`body`)'];
        yield 'fulltext key' => ['ADD FULLTEXT KEY ft_text (body)', 'ADD FULLTEXT KEY `ft_text` (`body`)'];
        yield 'fulltext without index' => ['ADD FULLTEXT ft_text (body)', 'ADD FULLTEXT KEY `ft_text` (`body`)'];
        yield 'spatial index' => ['ADD SPATIAL INDEX sp_place (place)', 'ADD SPATIAL KEY `sp_place` (`place`)'];
    }

    #[DataProvider('indexAdditions')]
    public function testAddIndex(string $alterOption, string $expectedOption): void
    {
        $this->matchCase('ALTER TABLE `users` ' . $alterOption, "ALTER TABLE `users`\n" . $expectedOption);
    }

    public function testSeveralAlterOptionsAreSeparatedByCommas(): void
    {
        $this->matchCase(
            'ALTER TABLE `users` ADD INDEX idx_email (email), ADD FULLTEXT INDEX ft_body (body), DROP COLUMN legacy',
            "ALTER TABLE `users`\nADD KEY `idx_email` (`email`),\nADD FULLTEXT KEY `ft_body` (`body`),\nDROP COLUMN `legacy`"
        );
    }

    public function testAddSeveralColumnsInParentheses(): void
    {
        $this->matchCase(
            'ALTER TABLE `users` ADD COLUMN (`a` int NOT NULL, `b` varchar(10) NULL)',
            "ALTER TABLE `users`\nADD COLUMN (`a` INT NOT NULL, `b` VARCHAR (10) NULL)"
        );
    }

    public function testDropOfKeys(): void
    {
        $this->matchCase(
            'ALTER TABLE `users` DROP PRIMARY KEY, DROP FOREIGN KEY fk_group, DROP INDEX idx_email, DROP KEY idx_name',
            "ALTER TABLE `users`\nDROP PRIMARY KEY,\nDROP FOREIGN KEY `fk_group`,\nDROP INDEX `idx_email`,"
            . "\nDROP INDEX `idx_name`"
        );
    }

    public function testAddForeignKeyIsRefused(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('ADD FOREIGN KEY');

        (new AlterTable())->parse('ALTER TABLE `users` ADD FOREIGN KEY (group_id) REFERENCES groups (id)');
    }
}
