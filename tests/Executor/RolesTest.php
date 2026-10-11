<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Executor\Entities\Article;
use IfCastle\AQL\Executor\Entities\ArticleNote;
use IfCastle\AQL\Executor\Entities\ArticleRevision;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Relations as named roles (dev/design/aql-relations.md of the lib repository): Article has two roles to
 * ArticleRevision (draftRevision, publishedRevision), ArticleRevision has the role parent to itself.
 */
final class RolesTest extends SqlQueryExecutorTestCase
{
    public function testReferenceRelationIsNamedByItsRole(): void
    {
        $article                    = $this->entity(Article::entity());
        $relations                  = $article->getRelations();

        $this->assertSame(['draftRevision', 'publishedRevision'], \array_keys($relations));
        $this->assertSame(RelationInterface::INTEGRITY_DECLARED, $relations['draftRevision']->getIntegrity());
        $this->assertSame(RelationInterface::INTEGRITY_IMPLIED, $relations['publishedRevision']->getIntegrity());
        $this->assertSame(['fk_article_published', 'fk_edition_revision'], $relations['publishedRevision']->getDerivedFrom());
        $this->assertFalse($relations['publishedRevision']->isRequired());
        $this->assertTrue($this->entity(ArticleRevision::entity())->getRelations()['article']->isRequired());
    }

    public function testInheritedRolesKeepNameAndIntegrity(): void
    {
        $relations                  = $this->entity('SpecialArticle')->getRelations();

        $this->assertArrayHasKey('draftRevision', $relations);
        $this->assertSame(RelationInterface::INTEGRITY_IMPLIED, $relations['publishedRevision']->getIntegrity());
        $this->assertArrayNotHasKey('ArticleRevision', $relations);
    }

    public function testReferenceWithoutIdSuffixNeedsRole(): void
    {
        $this->expectException(EntityDescriptorException::class);
        $this->expectExceptionMessage('needs an explicit role');

        $this->entity(ArticleNote::entity());
    }

    public function testFindRelationByEntityFailsWhenTwoRolesLeadThere(): void
    {
        $this->expectException(EntityDescriptorException::class);
        $this->expectExceptionMessage('draftRevision, publishedRevision');

        $this->entity(Article::entity())->findRelation(ArticleRevision::entity());
    }

    public static function roleJoins(): array
    {
        return [
            'role join with aliases' => [
                'SELECT a.id, p.title FROM Article a JOIN a.publishedRevision p WHERE a.id = 1',
                'SELECT `t0`.`id`, `t1`.`title` FROM `article` as `t0`
                INNER JOIN `article_revision` as `t1` ON (`t1`.`id` = `t0`.`publishedRevisionId`) WHERE `t0`.`id` = \'1\'',
            ],
            'two roles to one entity' => [
                'SELECT p.title AS published, d.title AS draft FROM Article a
                JOIN a.publishedRevision p LEFT JOIN a.draftRevision d',
                'SELECT `t1`.`title` as `published`, `t2`.`title` as `draft` FROM `article` as `t0`
                INNER JOIN `article_revision` as `t1` ON (`t1`.`id` = `t0`.`publishedRevisionId`)
                LEFT JOIN `article_revision` as `t2` ON (`t2`.`id` = `t0`.`draftRevisionId`)',
            ],
            'self join by role' => [
                'SELECT r.title, parent.title AS parentTitle FROM ArticleRevision r LEFT JOIN r.parent parent',
                'SELECT `t0`.`title`, `t1`.`title` as `parentTitle` FROM `article_revision` as `t0`
                LEFT JOIN `article_revision` as `t1` ON (`t1`.`id` = `t0`.`parentId`)',
            ],
            'chained roles' => [
                'SELECT g.title FROM ArticleRevision r JOIN r.parent p JOIN p.parent g WHERE r.id = 7',
                'SELECT `t2`.`title` FROM `article_revision` as `t0`
                INNER JOIN `article_revision` as `t1` ON (`t1`.`id` = `t0`.`parentId`)
                INNER JOIN `article_revision` as `t2` ON (`t2`.`id` = `t1`.`parentId`) WHERE `t0`.`id` = \'7\'',
            ],
            'role join without alias is named by the role' => [
                'SELECT publishedRevision.title FROM Article JOIN Article.publishedRevision',
                'SELECT `t1`.`title` FROM `article` as `t0`
                INNER JOIN `article_revision` as `t1` ON (`t1`.`id` = `t0`.`publishedRevisionId`)',
            ],
            'main alias and unqualified columns are one occurrence' => [
                'SELECT id, a.name FROM Article AS a WHERE name = "x"',
                'SELECT `t0`.`id`, `t0`.`name` FROM `article` as `t0` WHERE `t0`.`name` = \'x\'',
            ],
            'entity join with an alias' => [
                'SELECT b.title FROM Book b JOIN BookSection s WHERE s.title = "x"',
                'SELECT `t0`.`title` FROM `book` as `t0`
                INNER JOIN `book_section` as `t1` ON (`t1`.`id` = `t0`.`bookSectionId`) WHERE `t1`.`title` = \'x\'',
            ],
            'inherited role runs through the parent table' => [
                'SELECT m.subscription, s.title FROM Magazine m JOIN m.bookSection s',
                'SELECT `t2`.`subscription`, `t1`.`title` FROM `magazine` as `t2`
                INNER JOIN `book` as `t0` ON (`t0`.`id` = `t2`.`bookId`)
                INNER JOIN `book_section` as `t1` ON (`t1`.`id` = `t0`.`bookSectionId`)',
            ],
            'inherited one of two roles keeps the written JOIN' => [
                'SELECT s.badge, p.title FROM SpecialArticle s JOIN s.publishedRevision p',
                'SELECT `t2`.`badge`, `t1`.`title` FROM `special_article` as `t2`
                INNER JOIN `article` as `t0` ON (`t0`.`id` = `t2`.`articleId`)
                INNER JOIN `article_revision` as `t1` ON (`t1`.`id` = `t0`.`publishedRevisionId`)',
            ],
            'inherited role keeps the written LEFT JOIN' => [
                'SELECT s.badge, p.title FROM SpecialArticle s LEFT JOIN s.publishedRevision p',
                'SELECT `t2`.`badge`, `t1`.`title` FROM `special_article` as `t2`
                INNER JOIN `article` as `t0` ON (`t0`.`id` = `t2`.`articleId`)
                LEFT JOIN `article_revision` as `t1` ON (`t1`.`id` = `t0`.`publishedRevisionId`)',
            ],
            'inherited relation joined by entity' => [
                'SELECT subscription FROM Magazine JOIN BookSection',
                'SELECT `t0`.`subscription` FROM `magazine` as `t0`
                INNER JOIN `book` as `t2` ON (`t2`.`id` = `t0`.`bookId`)
                INNER JOIN `book_section` as `t1` ON (`t1`.`id` = `t2`.`bookSectionId`)',
            ],
            'alias inside a subquery' => [
                'SELECT title FROM BookSection WHERE id IN (SELECT bookSectionId FROM Book b WHERE b.title = "x")',
                'SELECT `t0`.`title` FROM `book_section` as `t0` WHERE `t0`.`id` IN
                (SELECT `t0_t0`.`bookSectionId` FROM `book` as `t0_t0` WHERE `t0_t0`.`title` = \'x\')',
            ],
            'role join inside a subquery uses the subquery aliases' => [
                'SELECT title FROM Book WHERE id IN
                (SELECT r.articleId FROM ArticleRevision r JOIN r.parent p WHERE p.title = "x")',
                'SELECT `t0`.`title` FROM `book` as `t0` WHERE `t0`.`id` IN (SELECT `t0_t0`.`articleId`
                FROM `article_revision` as `t0_t0`
                INNER JOIN `article_revision` as `t0_t1` ON (`t0_t1`.`id` = `t0_t0`.`parentId`) WHERE `t0_t1`.`title` = \'x\')',
            ],
            'reverse relation joined by entity is LEFT: a section may have no books' => [
                'SELECT s.title, b.title AS book FROM BookSection s, Book b',
                'SELECT `t0`.`title`, `t1`.`title` as `book` FROM `book_section` as `t0`
                LEFT JOIN `book` as `t1` ON (`t1`.`bookSectionId` = `t0`.`id`)',
            ],
            'written JOIN stays INNER on an optional relation' => [
                'SELECT * FROM Book JOIN BookProvider p',
                'SELECT * FROM `book` as `t0` INNER JOIN `book_provider` as `t1` ON (`t1`.`id` = `t0`.`bookProviderId`)',
            ],
            'written LEFT JOIN stays LEFT on a required relation' => [
                'SELECT * FROM Book LEFT JOIN BookSection',
                'SELECT * FROM `book` as `t0` LEFT JOIN `book_section` as `t1` ON (`t1`.`id` = `t0`.`bookSectionId`)',
            ],
            'ON is added to the role predicate' => [
                'SELECT a.id FROM Article a JOIN a.publishedRevision p ON (p.title = "x")',
                'SELECT `t0`.`id` FROM `article` as `t0`
                INNER JOIN `article_revision` as `t1`
                ON (`t1`.`id` = `t0`.`publishedRevisionId` AND `t1`.`title` = \'x\')',
            ],
            'ON with OR keeps its group next to the role predicate' => [
                'SELECT a.id FROM Article a LEFT JOIN a.draftRevision d ON (d.title = "x" OR d.title = "y")',
                'SELECT `t0`.`id` FROM `article` as `t0` LEFT JOIN `article_revision` as `t1`
                ON (`t1`.`id` = `t0`.`draftRevisionId` AND (`t1`.`title` = \'x\' OR `t1`.`title` = \'y\'))',
            ],
            'ON with one bracketed group on a role join' => [
                'SELECT a.id FROM Article a LEFT JOIN a.draftRevision d ON ((d.title = "x" OR d.id = 1))',
                'SELECT `t0`.`id` FROM `article` as `t0` LEFT JOIN `article_revision` as `t1`
                ON (`t1`.`id` = `t0`.`draftRevisionId` AND (`t1`.`title` = \'x\' OR `t1`.`id` = \'1\'))',
            ],
            'ON and dependent joins of an inherited role move to its last hop' => [
                'SELECT a.badge FROM SpecialArticle a LEFT JOIN a.publishedRevision p ON (p.title = "x") {
                    LEFT JOIN p.parent pp
                }',
                'SELECT `t2`.`badge` FROM `special_article` as `t2`
                INNER JOIN `article` as `t0` ON (`t0`.`id` = `t2`.`articleId`)
                LEFT JOIN `article_revision` as `t1`
                ON (`t1`.`id` = `t0`.`publishedRevisionId` AND `t1`.`title` = \'x\')
                LEFT JOIN `article_revision` as `t3` ON (`t3`.`id` = `t1`.`parentId`)',
            ],
            'ad hoc join looks up no relation' => [
                'SELECT b.title FROM Book b JOIN BookSection s ON (s.title = b.title)',
                'SELECT `t0`.`title` FROM `book` as `t0`
                INNER JOIN `book_section` as `t1` ON (`t1`.`title` = `t0`.`title`)',
            ],
            'ad hoc theta self join' => [
                'SELECT r.title, n.title AS nextTitle FROM ArticleRevision r
                LEFT JOIN ArticleRevision n ON (n.articleId = r.articleId AND n.id > r.id)',
                'SELECT `t0`.`title`, `t1`.`title` as `nextTitle` FROM `article_revision` as `t0`
                LEFT JOIN `article_revision` as `t1`
                ON (`t1`.`articleId` = `t0`.`articleId` AND `t1`.`id` > `t0`.`id`)',
            ],
            'role join after an ad hoc join' => [
                'SELECT p.title FROM Article a JOIN ArticleRevision r ON (r.articleId = a.id) JOIN r.parent p',
                'SELECT `t2`.`title` FROM `article` as `t0`
                INNER JOIN `article_revision` as `t1` ON (`t1`.`articleId` = `t0`.`id`)
                INNER JOIN `article_revision` as `t2` ON (`t2`.`id` = `t1`.`parentId`)',
            ],
        ];
    }

    #[DataProvider('roleJoins')]
    public function testRoleJoin(string $aql, string $sql): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor($aql, $sql));
    }

    public static function rejectedQueries(): array
    {
        return [
            'two roles, joined by entity' => [
                'SELECT * FROM Article, ArticleRevision',
                EntityDescriptorException::class,
                'draftRevision, publishedRevision',
            ],
            'self relation, joined by entity' => [
                'SELECT r.title FROM ArticleRevision r JOIN ArticleRevision p',
                EntityDescriptorException::class,
                'must be joined by its role',
            ],
            'entity name of a repeated entity' => [
                'SELECT ArticleRevision.title FROM ArticleRevision r JOIN r.parent p',
                QueryException::class,
                'is ambiguous',
            ],
            'unknown role' => [
                'SELECT * FROM Article a JOIN a.nothing n',
                QueryException::class,
                'has no role',
            ],
            'entity join next to a role join to the same entity' => [
                'SELECT * FROM Article a JOIN a.publishedRevision p JOIN ArticleRevision d',
                EntityDescriptorException::class,
                'draftRevision, publishedRevision, D.article',
            ],
            'cross reference and a reference back, from one side' => [
                'SELECT * FROM Novel JOIN Writer',
                EntityDescriptorException::class,
                'mainWriter, Writer.Novel',
            ],
            'cross reference and a reference back, from the other side' => [
                'SELECT * FROM Writer JOIN Novel',
                EntityDescriptorException::class,
                'Novel, Novel.mainWriter',
            ],
            'entity name of an aliased occurrence' => [
                'SELECT * FROM Article AS a WHERE Article.name = "x"',
                QueryException::class,
                'use the alias',
            ],
            'entity name of an occurrence reached by another path' => [
                'SELECT r.title, Article.name FROM ArticleRevision r JOIN r.parent p JOIN p.article x',
                QueryException::class,
                'use the alias',
            ],
            'keyword is not an alias' => [
                'SELECT * FROM Book offset',
                ParseException::class,
                'OFFSET',
            ],
            'ad hoc join across storages' => [
                'SELECT * FROM Book b JOIN BookFiles f ON (f.bookId = b.id)',
                QueryException::class,
                'across storages',
            ],
            'ON on a role join across storages' => [
                'SELECT * FROM BookFiles f JOIN f.book b ON (b.title = "x")',
                QueryException::class,
                'across storages',
            ],
            'repeated alias' => [
                'SELECT * FROM ArticleRevision r JOIN r.parent r',
                \Throwable::class,
                'used twice',
            ],
        ];
    }

    #[DataProvider('rejectedQueries')]
    public function testRejectedQuery(string $aql, string $exception, string $message): void
    {
        $this->expectException($exception);
        $this->expectExceptionMessage($message);

        $this->executeCase(new SqlQueryCaseDescriptor($aql, ''));
    }

    private function entity(string $name): \IfCastle\AQL\Entity\EntityInterface
    {
        return $this->getDiContainer()->resolveDependency(EntityFactoryInterface::class)->getEntity($name);
    }
}
