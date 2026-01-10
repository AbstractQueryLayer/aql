<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Resolver;

use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Parameter\Parameter;
use IfCastle\AQL\Dsl\Sql\Parameter\ParameterInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumn;
use IfCastle\AQL\Entity\Relation\DirectRelationInterface;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use IfCastle\AQL\Executor\Plan\ResultReaderInterface;
use IfCastle\AQL\Executor\Plan\SqlQueryCommandInterface;
use IfCastle\AQL\Result\ResultInterface;

class SqlRelationResolverForUpdate implements ResultReaderInterface
{
    /**
     *
     *
     * @throws QueryException
     */
    public static function resolve(SqlQueryCommandInterface $leftCommand, DirectRelationInterface $relation, SqlQueryCommandInterface $fromSelectCommand): void
    {
        // Explanation:
        // leftEntity related to rightEntity.
        // rightEntity depends on leftEntity
        // So
        // UPDATE leftEntity, rightEntity SET leftEntity.name = 'name', rightEntity.title = 'title' WHERE...
        // means:
        // SELECT * FROM leftEntity, rightEntity WHERE ... -- normalization query
        // then
        // UPDATE rightEntity SET rightEntity.title = 'title' WHERE primaryKey = ...
        // UPDATE leftEntity SET leftEntity.name = 'name' WHERE primaryKey = ...
        //

        $tuple                      = $fromSelectCommand->getContainedSqlQuery()->getTuple();
        $where                      = $leftCommand->getContainedSqlQuery()->getWhere();
        $leftEntityName             = $leftCommand->getContainedSqlQuery()->getMainEntityName();
        $columns                    = [];
        $leftColumns                = $relation->getLeftKey()->getKeyColumns();
        $parameters                 = [];
        $offset                     = 0;

        // 1. Add to $fromSelectQuery relation keys
        foreach ($relation->getRightKey()->getKeyColumns() as $column) {
            $columns[$column]       = new TupleColumn(new Column($column, $leftEntityName));
            $parameters[$column]    = new Parameter($column);
            $tuple->addHiddenColumn($columns[$column]);
            $where->equal(new Column($leftColumns[$offset]), $parameters[$column]);
        }

        $fromSelectCommand->getResultProcessingPlan()->addResultReader(new self($columns, $parameters));
    }

    /**
     * @param TupleColumn[]        $tupleColumns
     * @param ParameterInterface[] $parameters
     */
    public function __construct(
        protected array $tupleColumns,
        protected array $parameters
    ) {}

    #[\Override]
    public function dispose(): void
    {
        $this->tupleColumns         = [];
    }

    #[\Override]
    public function __invoke(...$args): void
    {
        $this->readResult(...$args);
    }

    #[\Override]
    public function readResult(ResultInterface $result): void
    {
        $aliases                    = [];

        foreach ($this->tupleColumns as $column => $tupleColumn) {
            $aliases[$column]       = $tupleColumn->resolveNode()->getAliasOrColumnName();
        }

        foreach ($result as $row) {
            foreach ($aliases as $column => $alias) {
                if (\array_key_exists($alias, $row)) {
                    $this->parameters[$column]->setParameterValue($row[$alias]);
                }
            }
        }
    }
}
