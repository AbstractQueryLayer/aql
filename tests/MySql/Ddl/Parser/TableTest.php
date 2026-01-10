<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Ddl\Parser;

use IfCastle\AQL\Dsl\Parser\SqlString;
use PHPUnit\Framework\TestCase;

class TableTest extends TestCase
{
    protected function matchCase(string $inputSql, string $expectedAql): void
    {
        $table                      = (new Table())->parse($inputSql);

        $this->assertEquals(SqlString::normalize($expectedAql), SqlString::normalize($table->getAql()));
    }

    public function testSimple(): void
    {
        $this->matchCase(
            /** @lang aql */ "CREATE TABLE `test` (
  `id` varchar(36) NOT NULL,
  `book_id` varchar(36) NOT NULL DEFAULT 'TEST0101',
  `section_id` char(36) DEFAULT 456,
  `group_id` varchar(36) NOT NULL,
  `enum` ENUM ('item1','item2','item3','item4') NOT NULL DEFAULT 'item1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at date',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_key` (`book_id`,`section_id`),
  KEY `test_fk0` (`book_id`),
  KEY `test_fk1` (`section_id`),
  KEY `test_fk2` (`group_id`),
  CONSTRAINT `test_fk0` FOREIGN KEY (`book_id`) REFERENCES `book` (`id`),
  CONSTRAINT `test_fk1` FOREIGN KEY (`section_id`) REFERENCES `section` (`id`),
  CONSTRAINT `test_fk2` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Test table';
",
            /** @lang aql */ "CREATE TABLE `test` (
`id` VARCHAR (36) NOT NULL,
`book_id` VARCHAR (36) NOT NULL DEFAULT 'TEST0101',
`section_id` CHAR (36) NULL DEFAULT 456,
`group_id` VARCHAR (36) NOT NULL,
`enum` ENUM ('item1','item2','item3','item4') NOT NULL DEFAULT 'item1',
`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at date',
PRIMARY KEY (`id`),
UNIQUE KEY `uniq_key` (`book_id`,`section_id`),
KEY `test_fk0` (`book_id`),
KEY `test_fk1` (`section_id`),
KEY `test_fk2` (`group_id`),
CONSTRAINT `test_fk0` FOREIGN KEY (`book_id`) REFERENCES `book` (`id`),
CONSTRAINT `test_fk1` FOREIGN KEY (`section_id`) REFERENCES `section` (`id`),
CONSTRAINT `test_fk2` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`)
) COMMENT 'Test table'"
        );
    }
}
