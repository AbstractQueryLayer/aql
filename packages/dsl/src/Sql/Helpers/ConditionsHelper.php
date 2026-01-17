<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Helpers;

use IfCastle\AQL\Dsl\Node\Exceptions\NodeException;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Constant\Variable;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperationInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\SingleOperation;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;

final class ConditionsHelper
{
    /**
     * Grouped simple filters by subject.
     *
     * The method imposes restrictions on the complexity of WHERE expressions.
     * It only supports AND operations,
     * and only LROperation expressions where the left side is a column and the right side is a constant.
     *
     * The method is designed to interpret SQL-LIKE queries with simple filters.
     *
     *
     * @return  array<string, array<string,ConstantInterface>>
     * @throws  NodeException
     */
    public static function groupFiltersBySubject(QueryInterface $query): array
    {
        $result                     = [];

        $where                      = $query->getWhere()?->resolveSubstitution();

        if ($where instanceof ConditionsInterface === false) {
            throw new NodeException([
                'template'          => 'Wrong WHERE node, expected ConditionsInterface, got {type}',
                'storage'           => $query->getQueryStorage(),
                'query'             => $query->getAql(),
                'type'              => \get_debug_type($where),
            ]);
        }

        foreach ($where->getChildNodes() as $operation) {

            $operation              = $operation->resolveSubstitution();

            // We support only left-right operations for Redis
            if ($operation instanceof LROperationInterface === false) {
                throw new NodeException([
                    'template'          => 'Expression not supported by {storage}, expected LROperationInterface, got {type}',
                    'storage'           => $query->getQueryStorage(),
                    'query'             => $query->getAql(),
                    'type'              => \get_debug_type($operation),
                ]);
            }

            $left                   = $operation->getLeftNode()?->resolveSubstitution();
            $right                  = $operation->getRightNode()?->resolveSubstitution();

            if ($left instanceof ColumnInterface === false) {
                throw new NodeException([
                    'template'          => 'Where expression not supported by {storage}, expected left.ColumnInterface, got {type}',
                    'storage'           => $query->getQueryStorage(),
                    'query'             => $query->getAql(),
                    'type'              => \get_debug_type($left),
                ]);
            }

            if ($right instanceof ConstantInterface === false) {
                throw new NodeException([
                    'template'          => 'Where expression not supported by {storage}, expected right.ConstantInterface, got {type}',
                    'storage'           => $query->getQueryStorage(),
                    'query'             => $query->getAql(),
                    'type'              => \get_debug_type($right),
                ]);
            }

            if (false === \array_key_exists($left->getSubjectAlias(), $result)) {
                $result[$left->getSubjectAlias()] = [];
            }

            if (\array_key_exists($left->getColumnName(), $result[$left->getSubjectAlias()])) {
                throw new NodeException([
                    'template'          => 'Where expression not supported by {storage}, duplicate filter {column} in {subject}',
                    'storage'           => $query->getQueryStorage(),
                    'query'             => $query->getAql(),
                    'column'            => $left->getColumnName(),
                    'subject'           => $left->getSubject(),
                ]);
            }

            $result[$left->getSubjectAlias()][$left->getColumnName()] = $right;
        }

        return $result;
    }

    public static function walkByColumnValue(ConditionsInterface $conditions, string|ColumnInterface $column): \Iterator
    {
        foreach ($conditions->resolveSubstitution()->getChildNodes() as $childNode) {

            $isChanged              = false;
            $childNode              = $childNode->resolveSubstitution();

            if ($childNode instanceof SingleOperation) {

                $left               = $childNode->getExpression();

                if ($left instanceof ColumnInterface && $left->isEqual($column)) {
                    $isChanged      = yield new Variable(true);
                }
            } elseif ($childNode instanceof ConditionsInterface) {
                $isChanged          = yield from self::walkByColumnValue($childNode, $column);
            } elseif ($childNode instanceof LROperationInterface) {
                $left               = $childNode->getLeftNode();

                if ($left instanceof ColumnInterface && $left->isEqual($column)) {
                    $isChanged      = yield $childNode->getRightNode();
                }
            }

            if ($isChanged === true) {
                $childNode->needTransform();
                $conditions->needTransform();
            }
        }
    }
}
