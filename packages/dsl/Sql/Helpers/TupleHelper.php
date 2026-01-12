<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Helpers;

use IfCastle\AQL\Dsl\Node\Exceptions\NodeException;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumnInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleInterface;

final class TupleHelper
{
    /**
     * Returns a list of tuple columns grouped by subject
     * This method does not allow complex elements to be used in a tuple other than as a column.
     *
     * @throws NodeException
     */
    public static function groupTupleColumnsBySubject(QueryInterface $query): array
    {
        $tuple                      = $query->getTuple()->resolveSubstitution();

        if ($tuple instanceof TupleInterface === false) {
            throw new NodeException([
                'template'          => 'Wrong TUPLE node, expected TupleInterface, got {type}',
                'storage'           => $query->getQueryStorage(),
                'query'             => $query->getAql(),
                'type'              => \get_debug_type($tuple),
            ]);
        }

        $result                     = [];

        foreach ($tuple->getChildNodes() as $column) {

            $column                 = $column->resolveSubstitution();

            if ($column instanceof TupleColumnInterface) {
                $expression         = $column->getExpression();

                if ($expression instanceof ColumnInterface) {
                    $subject        = $expression->getSubjectAlias() ?? '';
                    $result[$subject][] = $column;
                    continue;
                }
            }

            throw new NodeException([
                'template'          => 'Tuple column should be a TupleColumnI with ColumnI expression got {node}',
                'node'              => \get_debug_type($column),
            ]);
        }

        return $result;
    }

    public static function substituteTupleExpression(
        NodeInterface $substitution,
        NodeInterface $expression,
        NodeInterface $tupleColumn,
        string $columnName
    ): TupleColumnInterface {
        if ($tupleColumn instanceof TupleColumnInterface === false) {
            throw new NodeException([
                'template'          => 'Wrong parent node: should be a TupleColumnInterface got {node}',
                'node'              => \get_debug_type($tupleColumn),
            ]);
        }

        $tupleColumn->setAliasIfUndefined($columnName);
        $expression->setSubstitution($substitution);

        return $tupleColumn;
    }
}
