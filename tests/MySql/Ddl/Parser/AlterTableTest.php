<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Ddl\Parser;

use IfCastle\AQL\Dsl\Parser\SqlString;
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
                CHANGE COLUMN `email_verified_at` `email_verified_at` TIMESTAMP NULL DEFAULT NULL AFTER `name`
                CHANGE COLUMN `password` `password` VARCHAR (200) NOT NULL DEFAULT '' AFTER `email_verified_at`
                CHANGE COLUMN `remember_token` `remember_token` VARCHAR (100) NOT NULL DEFAULT '2'
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

}
