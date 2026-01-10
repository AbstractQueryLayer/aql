<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Dsl\Sql\Parameter\Parameter;
use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;
use IfCastle\AQL\Dsl\Sql\Query\Select;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use IfCastle\AQL\Storage\SomeStorageMock;

class SqlQueryExecutorTest extends SqlQueryExecutorTestCase
{
    public function testSelectSimple(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT * FROM Book',
            /** @lang aql */'SELECT * FROM `book` as `t0`',
        ),
        );
    }

    public function testSelectWithTuple(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT id, title FROM Book',
            /** @lang aql */'SELECT `t0`.`id`, `t0`.`title` FROM `book` as `t0`',
        ),
        );
    }

    public function testSelectWithTupleAndFilters(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT id, title FROM Book WHERE id="123"',
            /** @lang aql */'SELECT `t0`.`id`, `t0`.`title` FROM `book` as `t0` WHERE `t0`.`id` = \'123\'',
        ),
        );
    }

    public function testSelectWithOrderByGroupBy(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT * FROM Book GROUP BY id, title ORDER BY id',
            /** @lang aql */'SELECT * FROM `book` as `t0` GROUP BY `t0`.`id`, `t0`.`title` ORDER BY `t0`.`id`',
        ),
        );
    }

    public function testSelectWithLimit(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT * FROM Book LIMIT 1',
            /** @lang aql */'SELECT * FROM `book` as `t0` LIMIT 1',
        ),
        );
    }

    public function testSelectWithLimitOffset(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT * FROM Book LIMIT 5,100',
            /** @lang aql */'SELECT * FROM `book` as `t0` LIMIT 5,100',
        ),
        );
    }

    public function testSelectWithJoin(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT * FROM Book, BookSection',
            /** @lang aql */'SELECT * FROM `book` as `t0` INNER JOIN `book_section` as `t1` ON (`t1`.`id` = `t0`.`bookSectionId`)',
        ),
        );
    }

    public function testSelectWithJoinAndTuple(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT Book.title, BookSection.title as sectionTitle FROM Book, BookSection',
            /** @lang aql */'SELECT `t0`.`title`, `t1`.`title` as `sectionTitle` FROM `book` as `t0` 
            INNER JOIN `book_section` as `t1` ON (`t1`.`id` = `t0`.`bookSectionId`)',
        ),
        );
    }

    /**
     * The order of aliases must match the definitions in the FROM section.
     */
    public function testSelectWithJoinAndTupleReverse(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT BookSection.title as sectionTitle, Book.title FROM Book, BookSection',
            /** @lang aql */'SELECT `t1`.`title` as `sectionTitle`, `t0`.`title` FROM `book` as `t0` 
            INNER JOIN `book_section` as `t1` ON (`t1`.`id` = `t0`.`bookSectionId`)',
        ),
        );
    }

    public function testSelectWithJoinAndTupleLeftJoin(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT Book.title, BookProvider.name FROM Book',
            /** @lang aql */'SELECT `t0`.`title`, `t1`.`name` FROM `book` as `t0` 
            LEFT JOIN `book_provider` as `t1` ON (`t1`.`id` = `t0`.`bookProviderId`)',
        ),
        );
    }


    public function testSelectWithTupleEntities(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT Book.title, BookSection.title as sectionTitle FROM Book',
            /** @lang aql */'SELECT `t0`.`title`, `t1`.`title` as `sectionTitle` FROM `book` as `t0` 
            INNER JOIN `book_section` as `t1` ON (`t1`.`id` = `t0`.`bookSectionId`)',
        ),
        );
    }

    public function testSelectWithJoinAndWhere(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT * FROM Book, BookSection WHERE Book.title="example"',
            /** @lang aql */'SELECT * FROM `book` as `t0` 
            INNER JOIN `book_section` as `t1` ON (`t1`.`id` = `t0`.`bookSectionId`) 
            WHERE `t0`.`title` = \'example\'',
        ),
        );
    }

    public function testSelectWithSubquery(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT (SELECT title FROM BookSection) as title FROM Book',
            /** @lang aql */'SELECT
            (SELECT `t0_t0`.`title` FROM `book_section` as `t0_t0` WHERE `t0_t0`.`id` = `t0`.`bookSectionId` LIMIT 1)
            as `title`
            FROM `book` as `t0`',
        ),
        );
    }

    public function testSelectWithSubqueryJoin(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT (SELECT title FROM BookSection, Book) as title FROM Book',
            /** @lang aql */'SELECT 
            (SELECT `t0_t0`.`title` FROM `book_section` as `t0_t0` 
                INNER JOIN `book` as `t0_t1` ON (`t0_t1`.`bookSectionId` = `t1`.`id`) WHERE `t0_t0`.`id` = `t0`.`bookSectionId` LIMIT 1) 
            as `title` FROM `book` as `t0`',
        ),
        );
    }

    public function testSelectWithNestedSubquery(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT (SELECT (SELECT title FROM Book) FROM BookSection) as title FROM Book',
            /** @lang aql */'SELECT 
            (SELECT 
                (SELECT `t0_t0_t0`.`title` FROM `book` as `t0_t0_t0`
                WHERE `t0_t0_t0`.`bookSectionId` = `t0_t0`.`id` LIMIT 1)
            FROM `book_section` as `t0_t0` WHERE `t0_t0`.`id` = `t0`.`bookSectionId` LIMIT 1) 
            as `title` FROM `book` as `t0`',
        ),
        );
    }

    public function testSelectWithFilterSubquery(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT * FROM Book WHERE title IN (SELECT title FROM BookSection)',
            /** @lang aql */'SELECT * FROM `book` as `t0` WHERE `t0`.`title`
            IN (SELECT `t0_t0`.`title` FROM `book_section` as `t0_t0`)',
        ),
        );
    }

    public function testSelectWithFilterToInnerJoin(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT * FROM Book WHERE BookSection.title = "title"',
            /** @lang aql */'SELECT * FROM `book` as `t0` 
            INNER JOIN `book_section` as `t1` ON (`t1`.`id` = `t0`.`bookSectionId`) 
            WHERE `t1`.`title` = \'title\'',
        ),
        );
    }

    public function testSelectFromDerived(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT * FROM (SELECT * FROM Book) as my',
            /** @lang aql */'SELECT * FROM (SELECT * FROM `book` as `my_t0`) as `my`',
        ),
        );
    }

    public function testSelectFromNestedDerived(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT * FROM (SELECT * FROM (SELECT * FROM Book) as my) as my2',
            /** @lang aql */'SELECT * FROM (SELECT * FROM (SELECT * FROM `book` as `my_t0`) as `my`) as `my2`',
        ),
        );
    }

    public function testSelectWithTupleToDerived(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT title FROM (SELECT * FROM Book) as my',
            /** @lang aql */'SELECT `my`.`title` FROM (SELECT * FROM `book` as `my_t0`) as `my`',
        ),
        );
    }

    public function testSelectWithFilterToDerived(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT * FROM (SELECT * FROM Book) as my WHERE my.title = "title"',
            /** @lang aql */'SELECT * FROM (SELECT * FROM `book` as `my_t0`) as `my` WHERE `my`.`title` = \'title\'',
        ),
        );
    }

    public function testInsert(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ 'INSERT INTO Book (title) VALUES ("The Great Gatsby")',
            /** @lang aql */ 'INSERT INTO `book` (`title`) VALUES (\'The Great Gatsby\')',
        ));
    }

    public function testUpdate(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ 'UPDATE Book SET price = 19.99 WHERE id = 123',
            /** @lang aql */ 'UPDATE `book` as `t0` SET `t0`.`price` = \'19.99\' WHERE `t0`.`id` = \'123\'',
        ));
    }

    public function testMultipleUpdate(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ 'UPDATE Book, BookSection SET price = 19.99 WHERE id = 123',
            /** @lang aql */ 'UPDATE `book` as `t0`, '
            . 'INNER JOIN `book_section` as `t1` ON (`t1`.`id` = `t0`.`bookSectionId`)
            SET `t0`.`price` = \'19.99\' WHERE `t0`.`id` = \'123\'',
        ));
    }

    public function testDelete(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ 'DELETE FROM Book WHERE id = 123',
            /** @lang aql */ 'DELETE FROM `book` as `t0` WHERE `t0`.`id` = \'123\'',
        ));
    }

    public function testMultipleDelete(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ 'DELETE Book FROM Book, BookSection WHERE id = 123',
            /** @lang aql */ 'DELETE `t0` FROM `book` as `t0`
            INNER JOIN `book_section` as `t1` ON (`t1`.`id` = `t0`.`bookSectionId`)
            WHERE `t0`.`id` = \'123\'',
        ));
    }

    public function testSelectWithCrossReferenceVirtualProperty(): void
    {
        $plan                       = new PlanCaseDescriptor(/** @lang aql */ 'SELECT id, authorList FROM Book');

        $plan->addStoredResult(/** @lang aql */"SELECT `t0`.`id`, '' as `authorList`, `t0`.`id` as `@0` FROM `book` as `t0`", [
            ['id' => 1, 'authorList' => '', '@0' => 1],
            ['id' => 2, 'authorList' => '', '@0' => 2],
            ['id' => 3, 'authorList' => '', '@0' => 3],
        ]);

        $plan->addStoredResult(/** @lang aql */"SELECT *, `t1`.`bookId` as `@0` FROM `author` as `t0` 
            INNER JOIN `book_to_author` as `t1` ON (`t1`.`authorId` = `t0`.`id`) 
            WHERE `t1`.`bookId` IN ('1', '2', '3')",
            [
                ['id' => 1, 'name' => 'Author1', 'created_at' => '2023-01-01 00:00:00', '@0' => 1],
                ['id' => 2, 'name' => 'Author2', 'created_at' => '2023-01-02 00:00:00', '@0' => 2],
                ['id' => 3, 'name' => 'Author3', 'created_at' => '2023-01-03 00:00:00', '@0' => 3],
            ],
        );

        $plan->expectedResult       = [
            ['id' => 1, 'authorList' => [['id' => 1, 'name' => 'Author1', 'created_at' => '2023-01-01 00:00:00']]],
            ['id' => 2, 'authorList' => [['id' => 2, 'name' => 'Author2', 'created_at' => '2023-01-02 00:00:00']]],
            ['id' => 3, 'authorList' => [['id' => 3, 'name' => 'Author3', 'created_at' => '2023-01-03 00:00:00']]],
        ];

        $this->executePlan($plan);
    }

    public function testSelectWithCrossReferenceInTheTuple(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ 'SELECT id, Author.name FROM Book, Author',
            /** @lang aql */ 'SELECT `t0`.`id`, `t1`.`name` FROM `book` as `t0` 
            INNER JOIN `book_to_author` as `t2` ON (`t2`.`bookId` = `t0`.`id`) 
            INNER JOIN `author` as `t1` ON (`t1`.`id` = `t2`.`authorId`)',
        ));
    }

    public function testSelectWithCrossReferenceInTheFilter(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Book, Author WHERE Author.name = "Author1"',
            /** @lang aql */ 'SELECT * FROM `book` as `t0` 
            INNER JOIN `book_to_author` as `t2` ON (`t2`.`bookId` = `t0`.`id`) 
            INNER JOIN `author` as `t1` ON (`t1`.`id` = `t2`.`authorId`)
            WHERE `t1`.`name` = \'Author1\'',
        ));
    }

    public function testSelectWithCrossReferenceInTheFilterAsSubquery(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Book WHERE Author.name = "Author1"',
            /** @lang aql */ 'SELECT * FROM `book` as `t0` WHERE `t0`.`id` IN 
        (SELECT `t0_t0`.`bookId` FROM `book_to_author` as `t0_t0` WHERE `t0_t0`.`authorId` IN 
        (SELECT `t0_t0_t0`.`id` FROM `author` as `t0_t0_t0` WHERE `t0_t0_t0`.`name` = \'Author1\'))',
        ));
    }

    public function testSelectWithCrossReferenceInTheFilterAsSubqueryMultiple(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ 'SELECT * FROM Book WHERE Author.name = "Author1" AND Author.id = 15',
            /** @lang aql */ 'SELECT * FROM `book` as `t0` WHERE `t0`.`id` IN 
        (SELECT `t0_t0`.`bookId` FROM `book_to_author` as `t0_t0` WHERE `t0_t0`.`authorId` IN 
        (SELECT `t0_t0_t0`.`id` FROM `author` as `t0_t0_t0` WHERE `t0_t0_t0`.`name` = \'Author1\' AND `t0_t0_t0`.`id` = \'15\'))',
        ));
    }

    public function testSelectWithInheritedEntity(): void
    {
        //
        // We try to select property from parent entity,
        // and we expect that it will be selected from the parent table
        //

        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ 'SELECT title FROM Magazine WHERE id = 123',
            /** @lang aql */ 'SELECT `t1`.`title` FROM `magazine` as `t0`
            INNER JOIN `book` as `t1` ON (`t1`.`id` = `t0`.`bookId`) WHERE `t0`.`id` = \'123\'',
        ));
    }

    public function testSelectWithEncodedProperty(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ 'SELECT encodedProperty FROM Author WHERE id = 123',
            /** @lang aql */ 'SELECT `t0`.`encodedProperty` FROM `author` as `t0` WHERE `t0`.`id` = \'123\'',
        ));

    }

    public function testSelectWithFilterEncodedProperty(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ 'SELECT name FROM Author WHERE encodedProperty = "test"',
            /** @lang aql */ 'SELECT `t0`.`name` FROM `author` as `t0` WHERE `t0`.`encodedProperty` = \'dGVzdA==\'',
        ));
    }

    public function testSelectWithSeveralStorages(): void
    {
        $plan                       = new PlanCaseDescriptor(/** @lang aql */ 'SELECT * FROM BookFiles
            INNER JOIN Book
            WHERE Book.title = "The Great Gatsby"',
        );

        $plan->addStoredResult(/** @lang aql */'SELECT *, [[BookFiles.bookId]] FROM BookFiles
        {query: SELECT * FROM Book WHERE (Book.id = BookFiles.bookId) AND Book.title = "The Great Gatsby"}', [
            ['id' => 1, 'title' => 'File1', 'created_at' => '2001-01-01 00:00:00', 'updated_at' => null, '@0' => 1],
            ['id' => 2, 'title' => 'File2', 'created_at' => '2002-02-02 00:00:00', 'updated_at' => null, '@0' => 2],
        ], SomeStorageMock::NAME);

        $plan->addStoredResult(/** @lang aql */'SELECT * FROM `book` as `t0`',
            [
                ['id' => 1, 'name' => 'Author1', 'created_at' => '2023-01-01 00:00:00', '@0' => 1],
                ['id' => 2, 'name' => 'Author2', 'created_at' => '2023-01-02 00:00:00', '@0' => 2],
                ['id' => 3, 'name' => 'Author3', 'created_at' => '2023-01-03 00:00:00', '@0' => 3],
            ],
        );

        $plan->expectedResult       = [
            ['id' => 1, 'title' => 'File1', 'created_at' => '2001-01-01 00:00:00', 'updated_at' => null],
            ['id' => 2, 'title' => 'File2', 'created_at' => '2002-02-02 00:00:00', 'updated_at' => null],
        ];

        $this->executePlan($plan);
    }

    public function testQueryWithParameters(): void
    {
        $aqlQuery                   = Select::entity('Book')
            ->column('id', 'title')
            ->where('title', new Parameter('title'))
            ->where('price', new Parameter('price'))
            ->limitWith(new Parameter('limit'));

        $aqlQuery->applyParameters([
            'title'                 => 'The Great Gatsby',
            'price'                 => 19.99,
            'limit'                 => 5,
        ]);

        $queryExecutor              = $this->newSqlQueryExecutor();
        $sqlStorageMock             = $this->getSqlStorageMock();

        $sqlStorageMock->reset();
        $queryExecutor->executeQuery($aqlQuery);

        $this->assertSqlStrings(
            "SELECT `t0`.`id`, `t0`.`title`
                        FROM `book` as `t0`
                        WHERE `t0`.`title` = 'The Great Gatsby' AND `t0`.`price` = '19.99' LIMIT 0, 5",
            $sqlStorageMock->getLastSql() ?? 'no query', 'Sql should be equals',
        );
    }

    public function testQueryWithParametersInCycle(): void
    {
        $aqlQuery                   = Select::entity('Book')
            ->column('id', 'title')
            ->where('title', new Parameter('title'))
            ->where('price', new Parameter('price'))
            ->limitWith(new Parameter('limit'));

        $price                      = 19.99;

        $aqlQuery->applyParameters([
            'title'                 => 'The Great Gatsby',
            'price'                 => 19.99,
            'limit'                 => 5,
        ]);

        $queryExecutor              = $this->newSqlQueryExecutor();
        $sqlStorageMock             = $this->getSqlStorageMock();

        $sqlStorageMock->reset();

        for ($i = 0; $i < 3; $i++) {
            $price                  += $i;
            $aqlQuery->applyParameter('price', $price);
            $queryExecutor->executeQuery($aqlQuery);

            $this->assertSqlStrings(
                "SELECT `t0`.`id`, `t0`.`title`
                               FROM `book` as `t0`
                                WHERE `t0`.`title` = 'The Great Gatsby' AND `t0`.`price` = '$price' LIMIT 0, 5",
                $sqlStorageMock->getLastSql() ?? 'no query', 'Sql should be equals',
            );
        }
    }

    public function testUnion(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */'SELECT * FROM Book UNION SELECT * FROM BookSection',
            /** @lang aql */'SELECT * FROM `book` as `t0` UNION SELECT * FROM `book_section` as `t0`',
        ));
    }

    public function testComplexUnion(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ <<<AQL
                SELECT * FROM Book ORDER BY id
                UNION ALL
                SELECT * FROM Book, BookSection ORDER BY id
                UNION
                SELECT * FROM BookSection ORDER BY id
                AQL,
            /** @lang aql */ <<<SQL
                SELECT * FROM `book` as `t0` ORDER BY `t0`.`id`
                UNION ALL
                SELECT * FROM `book` as `t0`
                    INNER JOIN `book_section` as `t1` ON (`t1`.`id` = `t0`.`bookSectionId`)
                    ORDER BY `t0`.`id`
                UNION SELECT * FROM `book_section` as `t0` ORDER BY `t0`.`id`
                SQL,
        ));
    }

    public function testSimpleCte(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ <<<AQL
                WITH cte1 AS (SELECT FROM Book)
                SELECT title, isTop, price FROM cte1
                AQL,
            /** @lang aql */ <<<SQL
                WITH cte1 AS (SELECT `t0`.`title`, `t0`.`isTop`, `t0`.`price` FROM `book` as `t0`)
                SELECT `t0`.`title`, `t0`.`isTop`, `t0`.`price` FROM `cte1` as `t0`
                SQL,
        ));
    }

    public function testCteWithJoin(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ <<<AQL
                WITH cte1 AS (SELECT FROM Book)
                SELECT title, isTop, price FROM cte1, BookSection
                AQL,
            /** @lang aql */ <<<SQL
                WITH cte1 AS (SELECT `t0`.`title`, `t0`.`isTop`, `t0`.`price`, `t0`.`bookSectionId` FROM `book` as `t0`)
                SELECT `t0`.`title`, `t0`.`isTop`, `t0`.`price` FROM `cte1` as `t0`
                INNER JOIN `book_section` as `t1` ON (`t1`.`id` = `t0`.`bookSectionId`)
                SQL,
        ));
    }

    public function testRecursiveCte(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ <<<AQL
                WITH RECURSIVE cte1 AS (
                    SELECT FROM Book WHERE isTop = true
                    UNION ALL
                    SELECT FROM Book)
                SELECT title, isTop, price FROM cte1, BookSection
                AQL,
            /** @lang aql */ <<<SQL
                WITH RECURSIVE cte1 AS (SELECT `t0`.`title`, `t0`.`isTop`, `t0`.`price`, `t0`.`bookSectionId`
                    FROM `book` as `t0`
                    WHERE `t0`.`isTop` = TRUE
                    UNION ALL
                    SELECT FROM `book` as `t0`
                    )
                SELECT `t0`.`title`, `t0`.`isTop`, `t0`.`price` FROM `cte1` as `t0`
                INNER JOIN `book_section` as `t1` ON (`t1`.`id` = `t0`.`bookSectionId`)
                SQL,
        ));
    }

    public function testCteWithDerivedRef(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ <<<AQL
                WITH cte1 AS (SELECT FROM Book),
                cte2 AS ( SELECT FROM BookSection, cte1 )
                SELECT title, isTop, price FROM cte1
                AQL,
            /** @lang aql */ <<<SQL
                WITH
                 cte1 AS (SELECT `t0`.`title`, `t0`.`isTop`, `t0`.`price`, `t0`.`bookSectionId` FROM `book` as `t0`),
                 cte2 AS (SELECT FROM `book_section` as `t0`
                                 INNER JOIN `cte1` as `t1` ON (`t1`.`bookSectionId` = `t0`.`id`))
            
                SELECT `t0`.`title`, `t0`.`isTop`, `t0`.`price` FROM `cte1` as `t0`
                SQL,
        ));
    }

    public function testCteWithDerivedRefAndJoinCondition(): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor(
            /** @lang aql */ <<<AQL
                WITH cte1 AS (SELECT FROM Book),
                cte2 AS (
                    SELECT FROM BookSection
                    INNER JOIN cte1 ON (cte1.bookSectionId = BookSection.id)
                )
                SELECT title, isTop, price FROM cte1
                AQL,
            /** @lang aql */ <<<SQL
                WITH
                    cte1 AS (SELECT `t0`.`title`, `t0`.`isTop`, `t0`.`price`, `t0`.`bookSectionId` FROM `book` as `t0`),
                    cte2 AS (SELECT FROM `book_section` as `t0`
                                    INNER JOIN `cte1` as `t1` ON (`t1`.`bookSectionId` = `t0`.`id`))
            
                SELECT `t0`.`title`, `t0`.`isTop`, `t0`.`price` FROM `cte1` as `t0`
                SQL,
        ));
    }

    public function testCteWithoutMainQuery(): void
    {
        $this->expectException(TransformationException::class);
        $this->expectExceptionMessage('Main Query is not defined for CTE');

        $aql                        = (new AqlParser())->parse(
            /** @lang aql */'WITH orphan AS (SELECT FROM Book)'
        );

        $this->newSqlQueryExecutor()->executeQuery($aql);
    }

    public function testCteWithDifferentStorages(): void
    {
        $this->expectException(TransformationException::class);
        $this->expectExceptionMessage('CTE subqueries must have the same storage');

        $aql                        = (new AqlParser())->parse(
            /** @lang aql */<<<AQL
                WITH books AS (SELECT FROM Book),
                files AS (SELECT FROM BookFiles)
                SELECT id FROM books
                AQL
        );

        $this->newSqlQueryExecutor()->executeQuery($aql);
    }

    public function testCteWithInsertMainQuery(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Insert operation is not allowed for CTE');

        $aql                        = (new AqlParser())->parse(
            /** @lang aql */'WITH base AS (SELECT FROM Book) INSERT INTO Book SET title = "new"'
        );

        $this->newSqlQueryExecutor()->executeQuery($aql);
    }

}
