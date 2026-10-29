<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Ddl;

use IfCastle\AQL\Dsl\Ddl\ShowTables;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\MySql\Ddl\Parser\AlterTable;
use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A DDL statement written by toArray() and passed through JSON comes back from fromArray() as the same tree: the same
 * class, the same AQL and the same array when written again.
 */
final class DdlRoundTripTest extends TestCase
{
    /** @return iterable<string, array{\Closure(): NodeInterface, string}> */
    public static function statements(): iterable
    {
        yield 'alter table with a rename and options' => [
            static fn() => (new AlterTable())->parse(
                'ALTER TABLE `users` ENGINE = InnoDB, COMMENT = \'People\' RENAME COLUMN `mail` TO `email`,'
                . ' RENAME INDEX `mail_idx` TO `email_idx`, DROP COLUMN `legacy`, RENAME TO `members`'
            ),
            "ALTER TABLE `users`\nENGINE InnoDB COMMENT 'People', RENAME COLUMN `mail` TO `email`,"
            . "\nRENAME INDEX `mail_idx` TO `email_idx`,\nDROP COLUMN `legacy`,\nRENAME TO `members`",
        ];
        yield 'alter table with a changed column' => [
            static fn() => (new AlterTable())->parse(
                'ALTER TABLE `users` CHANGE COLUMN `name` `title` varchar(100) NOT NULL DEFAULT \'\' AFTER `id`'
            ),
            "ALTER TABLE `users`\nCHANGE COLUMN `name` `title` VARCHAR (100) NOT NULL DEFAULT '' AFTER `id`",
        ];
        yield 'alter table adding indexes' => [
            static fn() => (new AlterTable())->parse(
                'ALTER TABLE `notes` ADD PRIMARY KEY (`id`), ADD INDEX `idx_author` (`author_id`, `created`),'
                . ' ADD FULLTEXT INDEX `ft_body` (`title`, `body`)'
            ),
            "ALTER TABLE `notes`\nADD PRIMARY KEY (`id`),\nADD KEY `idx_author` (`author_id`,`created`),"
            . "\nADD FULLTEXT KEY `ft_body` (`title`,`body`)",
        ];
        yield 'alter table dropping keys' => [
            static fn() => (new AlterTable())->parse(
                'ALTER TABLE `notes` DROP PRIMARY KEY, DROP FOREIGN KEY `fk_author`, DROP INDEX `idx_author`'
            ),
            "ALTER TABLE `notes`\nDROP PRIMARY KEY,\nDROP FOREIGN KEY `fk_author`,\nDROP INDEX `idx_author`",
        ];
        yield 'show tables' => [static fn() => new ShowTables('user%', 'lib'), "SHOW TABLES IN lib LIKE 'user%'"];
    }

    /** @param \Closure(): NodeInterface $build */
    #[DataProvider('statements')]
    public function testAStatementRoundTrips(\Closure $build, string $expectedAql): void
    {
        $statement                  = $build();
        $json                       = \json_encode(ArrayTyped::serialize($statement), JSON_THROW_ON_ERROR);
        $restored                   = ArrayTyped::unserialize(\json_decode($json, true, flags: JSON_THROW_ON_ERROR));

        $this->assertInstanceOf($statement::class, $restored);
        $this->assertSame($expectedAql, $statement->getAql());
        $this->assertSame($expectedAql, $restored->getAql());
        $this->assertEquals(ArrayTyped::serialize($statement), ArrayTyped::serialize($restored));
    }
}
