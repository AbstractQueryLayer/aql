<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Resolver;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Query\Select;
use IfCastle\AQL\Entity\Relation\DirectRelationInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use IfCastle\AQL\Executor\Plan\ResultReaderInterface;
use IfCastle\AQL\Executor\Plan\SqlQueryCommandInterface;
use IfCastle\AQL\Result\ResultInterface;

class SqlRelationResolverForInsert implements ResultReaderInterface
{
    public static function resolve(NodeContextInterface $context, SqlQueryCommandInterface $leftCommand, DirectRelationInterface $relation, SqlQueryCommandInterface $rightCommand): void
    {
        //
        // Example:
        // INSERT articles, categories SET articles.name = 'test', categories.title='example'
        // should be executed as
        //
        // INSERT categories SET categories.title='example'
        // INSERT articles SET name='test', category_id = 'last_insert_id'
        //
        // where articles is right entity ($rightCommand)
        // and categories is left entity ($leftCommand)
        // $rightCommand depends on $leftCommand
        // So $leftCommand should run first
        //

        // reverse
        if ($relation->isLeftDependedOnRight()) {
            [$leftCommand, $rightCommand] = [$rightCommand, $leftCommand];
            $relation               = $relation->reverseRelation();
        }

        // Right depended on left
        // so first should be executed left command
        // and then right command
        $leftCommand->getResultProcessingPlan()->addResultReader(new self($context, $leftCommand, $relation, $rightCommand));
    }

    public function __construct(
        protected NodeContextInterface     $context,
        protected SqlQueryCommandInterface $leftCommand,
        protected DirectRelationInterface  $relation,
        protected SqlQueryCommandInterface $rightCommand
    ) {}

    #[\Override]
    public function dispose(): void {}

    #[\Override]
    public function __invoke(...$args): void
    {
        $this->readResult(...$args);
    }

    /**
     * @throws QueryException
     */
    #[\Override]
    public function readResult(ResultInterface $result): void
    {
        // 1. Define depended keys
        $leftColumns                = $this->relation->getLeftKey()->getKeyColumns();

        // 2. Find auto-inc column if exists
        $autoIncrementColumn        = $this->findAutoIncrement();

        // 2. Extract depended columns from Assigns
        $assigmentValues            = $this->leftCommand->getContainedSqlQuery()->findAssigmentValues(...$leftColumns);

        if ($autoIncrementColumn !== null && $result instanceof InsertedI) {
            $assigmentValues[$autoIncrementColumn] = $result->getInsertedId();
        }

        // equal true if all nodes are constant or NULL
        $areAllConstants            = true;
        $areAllDefined              = true;

        // 3. Resolve nodes
        \array_walk_recursive($assigmentValues, static function (NodeInterface|null &$node) use (&$areAllConstants, &$areAllDefined) {
            $node                   = $node?->resolveNode();

            if ($node !== null && $node instanceof ConstantInterface === false) {
                $areAllConstants    = false;
            }

            if ($node === null) {
                $areAllDefined      = false;
            }
        });

        if (false === $areAllDefined) {
            throw new QueryException([
                'query'             => $this->leftCommand->getContainedSqlQuery(),
                'template'          => 'Not all dependent values have been determined',
            ]);
        }

        // 4. If not all values have been computed so far, we will need an additional Select query to resolve the values.
        if (false === $areAllConstants) {
            $this->resolveWithSelect($result);
            return;
        }

        $assigment                  = $this->rightCommand->getContainedSqlQuery()->getAssigmentList();
        $offset                     = 0;
        $assigmentValues            = \array_values($assigmentValues);

        foreach ($this->relation->getRightKey()->getKeyColumns() as $column) {
            $assigment->addAssignment($column, $assigmentValues[$offset]);
            ++$offset;
        }
    }

    protected function findAutoIncrement(): ?string
    {
        return $this->context->getEntity($this->relation->getLeftEntityName())->findAutoIncrement()?->getName();
    }

    protected function resolveWithSelect(ResultInterface $result): void
    {
        // Build select like:
        // SELECT relation.keys FROM leftEntity WHERE primaryKeys

        $select                     = new Select(
            $this->relation->getLeftEntityName(),
            $this->relation->getLeftKey()->getKeyColumns()
        );

        $where                      = $select->getWhere();

        $leftEntity                 = $this->context->getEntity($this->relation->getLeftEntityName());
        $assigmentList              = $this->leftCommand->getContainedSqlQuery()->getAssigmentList();

        foreach ($leftEntity->getPrimaryKey()->getKeyColumns() as $column) {

            if ($leftEntity->getProperty($column)->isAutoIncrement() && $result instanceof InsertedI) {
                $value              = $result->getInsertedId();
            } else {
                $value              = $assigmentList->findAssignByColumn($column)->resolveNode();
            }

            $where->equal(new Column($column), $value);
        }

        $resolved                   = $this->context->getQueryExecutor()->executeQuery($select)->firstToArray();
        $resolved                   = \array_values($resolved);

        $assigmentList              = $this->rightCommand->getContainedSqlQuery()->getAssigmentList();
        $offset                     = 0;

        foreach ($this->relation->getRightKey()->getKeyColumns() as $column) {
            $assigmentList->addAssignment($column, new Constant($resolved[$offset]));
            ++$offset;
        }
    }
}
