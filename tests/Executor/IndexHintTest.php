<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Subject;
use IfCastle\Exceptions\UnexpectedValue;
use PHPUnit\Framework\Attributes\DataProvider;

/** `USE|FORCE|IGNORE INDEX (…)` after a subject and its alias, rendered after the table and its alias. */
final class IndexHintTest extends SqlQueryExecutorTestCase
{
    public static function hintedQueries(): array
    {
        return [
            'hint on the FROM table' => [
                'SELECT a.id FROM Article a USE INDEX (idx_article_draft) WHERE a.id < 10 ORDER BY a.id DESC',
                'SELECT `t0`.`id` FROM `article` as `t0` USE INDEX (`idx_article_draft`)
                WHERE `t0`.`id` < \'10\' ORDER BY `t0`.`id` DESC',
            ],
            'hint without an alias, several names and hints' => [
                'SELECT id FROM Article FORCE INDEX (idx_a, idx_b) IGNORE INDEX (PRIMARY)',
                'SELECT `t0`.`id` FROM `article` as `t0` FORCE INDEX (`idx_a`, `idx_b`) IGNORE INDEX (`PRIMARY`)',
            ],
            'hint on a role join' => [
                'SELECT p.title FROM Article a JOIN a.publishedRevision p USE INDEX (PRIMARY)',
                'SELECT `t1`.`title` FROM `article` as `t0`
                INNER JOIN `article_revision` as `t1` USE INDEX (`PRIMARY`) ON (`t1`.`id` = `t0`.`publishedRevisionId`)',
            ],
            'hint on an ad hoc join' => [
                'SELECT n.title FROM ArticleRevision r JOIN ArticleRevision n FORCE INDEX (idx_next) ON (n.id > r.id)',
                'SELECT `t1`.`title` FROM `article_revision` as `t0`
                INNER JOIN `article_revision` as `t1` FORCE INDEX (`idx_next`) ON (`t1`.`id` > `t0`.`id`)',
            ],
            'hint on a comma join' => [
                'SELECT * FROM Book, BookSection s USE INDEX (idx_section)',
                'SELECT * FROM `book` as `t0`
                INNER JOIN `book_section` as `t1` USE INDEX (`idx_section`) ON (`t1`.`id` = `t0`.`bookSectionId`)',
            ],
        ];
    }

    #[DataProvider('hintedQueries')]
    public function testHintIsRendered(string $aql, string $sql): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor($aql, $sql));
    }

    public static function malformedHints(): array
    {
        return [
            'no INDEX keyword'  => ['SELECT * FROM Article a USE (idx)', 'INDEX'],
            'empty name list'   => ['SELECT * FROM Article a USE INDEX ()', 'index name'],
            'unclosed list'     => ['SELECT * FROM Article a USE INDEX (idx WHERE id = 1', ')'],
        ];
    }

    #[DataProvider('malformedHints')]
    public function testMalformedHintIsRejected(string $aql, string $message): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage($message);

        $this->executeCase(new SqlQueryCaseDescriptor($aql, ''));
    }

    public function testUnknownHintKindIsRejected(): void
    {
        $this->expectException(UnexpectedValue::class);

        (new Subject('Article'))->addIndexHint('STRAIGHT', 'idx');
    }
}
