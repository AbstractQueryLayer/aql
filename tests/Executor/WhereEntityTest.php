<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * `WHERE ENTITY [EXCLUDE] E(…)`: a semi-join renders IN, an anti-join renders NOT EXISTS, because NOT IN returns
 * no row once the subquery yields a NULL key.
 */
final class WhereEntityTest extends SqlQueryExecutorTestCase
{
    public static function whereEntities(): array
    {
        return [
            'semi-join stays IN' => [
                'SELECT id FROM Book WHERE ENTITY BookSection(title = "x")',
                'SELECT `t0`.`id` FROM `book` as `t0` WHERE `t0`.`bookSectionId` IN
                (SELECT `t0_t0`.`id` FROM `book_section` as `t0_t0` WHERE `t0_t0`.`title` = \'x\')',
            ],
            'anti-join along a reference' => [
                'SELECT id FROM Book WHERE ENTITY EXCLUDE BookProvider',
                'SELECT `t0`.`id` FROM `book` as `t0` WHERE NOT EXISTS
                (SELECT `t0_t0`.`id` FROM `book_provider` as `t0_t0` WHERE `t0_t0`.`id` = `t0`.`bookProviderId`)',
            ],
            'anti-join against the referencing side, aliased' => [
                'SELECT a.name FROM Article a WHERE ENTITY EXCLUDE ArticleRevision(title = "x")',
                'SELECT `t0`.`name` FROM `article` as `t0` WHERE NOT EXISTS (SELECT `t0_t0`.`articleId`
                FROM `article_revision` as `t0_t0`
                WHERE (`t0_t0`.`articleId` = `t0`.`id`) AND (`t0_t0`.`title` = \'x\'))',
            ],
            'anti-join through a link table negates only the outer link' => [
                'SELECT id FROM Book WHERE ENTITY EXCLUDE Author(name = "x")',
                'SELECT `t0`.`id` FROM `book` as `t0` WHERE NOT EXISTS
                (SELECT `t0_t0`.`bookId` FROM `book_to_author` as `t0_t0`
                WHERE (`t0_t0`.`bookId` = `t0`.`id`) AND (`t0_t0`.`authorId` IN
                (SELECT `t0_t0_t0`.`id` FROM `author` as `t0_t0_t0` WHERE `t0_t0_t0`.`name` = \'x\')))',
            ],
        ];
    }

    #[DataProvider('whereEntities')]
    public function testWhereEntity(string $aql, string $sql): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor($aql, $sql));
    }
}
