<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Transformer;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Node\NodeTransformerIterator;
use IfCastle\AQL\Dsl\Node\RecursiveIteratorByNodeIterator;
use IfCastle\AQL\Dsl\Parser\Select as SelectParser;
use IfCastle\AQL\Dsl\QueryOptions;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Conditions\Conditions;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Query\Expression\From;
use IfCastle\AQL\Dsl\Sql\Query\Expression\GroupBy;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Join;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Limit;
use IfCastle\AQL\Dsl\Sql\Query\Expression\NodeList;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperation;
use IfCastle\AQL\Dsl\Sql\Query\Expression\OrderBy;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Subject;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where;
use IfCastle\AQL\Dsl\Sql\Query\Union;
use IfCastle\AQL\Dsl\Sql\Tuple\Tuple;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumn;
use IfCastle\AQL\Executor\Context\NodeContext;
use PHPUnit\Framework\TestCase;

class RecursiveIteratorByNodeIteratorTest extends TestCase
{
    public function testBasicIteration(): void
    {
        $query                      = (new SelectParser())
            ->parse(/** @lang aql */'SELECT column1, column2 FROM table1, table2 WHERE column1 = 1 AND column2 = 2');

        $context                    = new NodeContext($query);

        $iterator                   = new RecursiveIteratorByNodeIterator(new NodeTransformerIterator($query), $context);

        $walkedNodes                = [];

        foreach ($iterator as $node) {
            $walkedNodes[]          = $node::class . ': ' . $node->getAql();
        }

        $this->assertEquals([
            QueryOptions::class . ': ',
            Tuple::class . ': column1, column2',
            NodeList::class . ': column1, column2',
            TupleColumn::class . ': column1',
            Column::class . ': column1',
            TupleColumn::class . ': column2',
            Column::class . ': column2',
            NodeList::class . ': ',
            From::class . ': FROM Table1 INNER JOIN Table2',
            Subject::class . ': Table1',
            NodeList::class . ': INNER JOIN Table2',
            Join::class . ': INNER JOIN Table2',
            Subject::class . ': Table2',
            NodeList::class . ': ',
            Where::class . ': WHERE column1 = 1 AND column2 = 2',
            LROperation::class . ': column1 = 1',
            Column::class . ': column1',
            Constant::class . ': 1',
            LROperation::class . ': column2 = 2',
            Column::class . ': column2',
            Constant::class . ': 2',
            GroupBy::class . ': ',
            OrderBy::class . ': ',
            Conditions::class . ': ',
            Limit::class . ': ',
            Union::class . ': ',
            NodeList::class . ': ',
            GroupBy::class . ': ',
            OrderBy::class . ': ',
            Limit::class . ': ',
        ], $walkedNodes);
    }

    /**
     * The iterator should not traverse a node if it has already been normalized.
     *
     */
    public function testSubstitutionNoWalk(): void
    {
        $query                      = (new SelectParser())
            ->parse(/** @lang aql */'SELECT column1, column2 FROM table1, table2 WHERE column1 = 1 AND column2 = 2');

        $context                    = new NodeContext($query);

        $iterator                   = new RecursiveIteratorByNodeIterator(new NodeTransformerIterator($query), $context);

        $walkedNodes                = [];

        foreach ($iterator as $node) {
            /** @var NodeInterface $node */
            $node->substituteToNullNode();
            $walkedNodes[]          = $node::class;
        }

        $this->assertEquals([
            QueryOptions::class,
            Tuple::class,
            From::class,
            Where::class,
            GroupBy::class,
            OrderBy::class,
            Conditions::class,
            Limit::class,
            Union::class,
        ], $walkedNodes);
    }

    public function testCreateNormalizerIterator(): void
    {
        $query                      = (new SelectParser())
            ->parse(/** @lang aql */'SELECT column1, column2 FROM table1, table2');

        $context                    = new NodeContext($query);
        $iterator                   = new RecursiveIteratorByNodeIterator(new NodeTransformerIterator($query), $context);

        $context->defineTransformerIteratorFactory(static function (NodeInterface $node) {
            return new RecursiveIteratorByNodeIterator(new NodeTransformerIterator($node));
        });

        $walkedNodes                = [];

        foreach ($iterator as $node) {

            if ($node instanceof TupleColumn) {
                $node->asTransformed();
            }

            if ($node instanceof JoinInterface && $node->getSubject()->getSubjectName() === 'Table2') {
                $query->getTuple()->addTupleColumn(new TupleColumn(new Column('column3')));

                foreach ($context->createTransformerIterator($query->getTuple()) as $tupleNode) {

                    if ($tupleNode instanceof Column) {
                        $walkedNodes[] = $tupleNode->getColumnName();
                    }

                }
            }
        }

        $this->assertEquals(['column3'], $walkedNodes);
    }
}
