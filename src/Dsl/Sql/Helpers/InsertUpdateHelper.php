<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Helpers;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Parameter\Parameter;
use IfCastle\AQL\Dsl\Sql\Query\Expression\AssignmentListInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\NodeList;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\Assign;
use IfCastle\AQL\Dsl\Sql\Query\Expression\ValueListInterface;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;

final class InsertUpdateHelper
{
    /**
     * The method iterates over all occurrences of the assignment expressions
     * and returns rows of assigned values.
     *
     *
     */
    public static function walkAssignedValues(QueryInterface $query): \Iterator
    {
        $assignmentList             = $query->getAssigmentList()?->resolveSubstitution();

        if ($assignmentList instanceof AssignmentListInterface && $assignmentList->isListNotEmpty()) {

            $values                 = [];

            foreach ($assignmentList as $assign) {
                $assign             = $assign?->resolveSubstitution();

                if ($assign instanceof Assign) {
                    [$left, $value] = [$assign->getLeftNode(), $assign->getRightNode()->resolveSubstitution()];

                    if ($left instanceof ColumnInterface && $value instanceof ConstantInterface) {
                        $values[$left->getColumnName()] = $value->getConstantValue();
                    } elseif ($left instanceof ColumnInterface) {
                        $values[$left->getColumnName()] = null;
                    }
                }
            }

            yield $values;

            return;
        }

        $valueList                  = $query->getValueList()?->resolveSubstitution();

        if ($valueList instanceof ValueListInterface === false || $valueList->isListEmpty()) {
            return;
        }

        $columns                    = $valueList->getColumns();

        foreach ($valueList->getChildNodes() as $row) {

            $row                    = $row?->resolveSubstitution();

            if ($row instanceof NodeList === false) {
                continue;
            }

            $values                 = [];

            foreach ($row->getChildNodes() as $index => $value) {

                $value              = $value?->resolveSubstitution();

                if ($value instanceof ConstantInterface) {
                    $values[$columns[$index]->getColumnName()] = $value->getConstantValue();
                } else {
                    $values[$columns[$index]->getColumnName()] = null;
                }
            }

            yield $values;
        }
    }

    /**
     * The method iterates over all occurrences of the column in expressions
     * of the SET\VALUES type for queries of the INSERT UPDATE type.
     *
     * The method returns an iterator that returns an array of two elements:
     * 1. The first element is the value of the column in the current row.
     * 2. The second element is the expression of the SET\VALUES type for the current row.
     *
     *
     * @return \Iterator [ConstantI|ParameterI $columnValue, NodeList|Assign $parentNode, NodeI $valuesNode]
     */
    public static function walkColumnValues(QueryInterface $query, string|ColumnInterface $column): \Iterator
    {
        $assignmentList             = $query->getAssigmentList()?->resolveSubstitution();

        if ($assignmentList instanceof AssignmentListInterface && $assignmentList->isListNotEmpty()) {
            $assign                 = $assignmentList->findAssignByColumn($column)?->resolveSubstitution();

            if ($assign instanceof Assign) {
                yield [$assign->getRightNode()->resolveSubstitution(), $assign, $assignmentList];
            }

            return;
        }

        $valueList                  = $query->getValueList()?->resolveSubstitution();

        if ($valueList instanceof ValueListInterface === false || $valueList->isListEmpty()) {
            return;
        }

        foreach ($valueList->walkValues($column) as [$row, $set]) {

            if (!\is_array($row)) {
                continue;
            }

            $item                   = \array_shift($row);

            if ($item instanceof NodeInterface === false) {
                continue;
            }

            yield [$item->resolveSubstitution(), $set, $assignmentList];
        }
    }

    public static function extractColumnValues(QueryInterface $query, string|ColumnInterface $column): array
    {
        $values                     = [];

        foreach (self::walkColumnValues($query, $column) as [$columnValue]) {

            if ($columnValue instanceof ConstantInterface) {
                $values[]           = $columnValue->getConstantValue();
            }
        }

        return $values;
    }

    public static function extractLastColumnValue(QueryInterface $query, string|ColumnInterface $column): mixed
    {
        $value                      = null;

        foreach (self::extractColumnValues($query, $column) as $columnValue) {
            $value                  = $columnValue;
        }

        return $value;
    }

    /**
     * The method iterates over all occurrences of the column in expressions
     * and insert or replaces the value of the column in the current row.
     *
     * If $generator is specified, then the method calls it for each occurrence of the column.
     * The generator function prototype looks like this:
     * function (ConstantI|ParameterI $columnValue, NodeList|Assign $parentNode, NodeI $valuesNode) {}
     *
     *
     */
    public static function defineColumnValue(QueryInterface $query, string|ColumnInterface $column, ?NodeInterface $value = null, ?callable $generator = null, bool $insertNew = true): void
    {
        $wasFound                   = false;

        foreach (self::walkColumnValues($query, $column) as [$columnValue, $set, $valuesNode]) {
            $wasFound               = true;
            $set->needNormalize();
            $valuesNode->needNormalize();

            if ($generator !== null) {
                $generator($columnValue, $set, $valuesNode);
            } elseif ($value !== null) {
                $columnValue->setSubstitution($value)->needNormalize();
            }
        }

        if ($wasFound || $insertNew === false) {
            return;
        }

        // Insert new column if not found
        if ($query->getValueList()?->isListNotEmpty()) {

            $query->getValueList()->appendColumnWithGenerator($column, static function (NodeList $values) use ($generator, $column, $value) {
                if ($generator !== null) {
                    $node               = new Parameter($column instanceof ColumnInterface ? $column->getColumnName() : $column);
                    $values->addChildNode($node);
                    $generator($node, $values);
                } elseif ($value !== null) {
                    $values->addChildNode($value);
                }
            });

            $query->getValueList()->needTransform();

        } else {

            if ($generator !== null) {
                $node               = new Parameter($column instanceof ColumnInterface ? $column->getColumnName() : $column);
                $query->getAssigmentList()->addAssignment($column, $node);
                $generator($node, $query->getAssigmentList());
            } elseif ($value !== null) {
                $query->getAssigmentList()->addAssignment($column, $value);
            }

            $query->getAssigmentList()->needTransform();
        }
    }
}
