<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Helpers;

use IfCastle\AQL\Dsl\Node\NodeHelper;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\JoinConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\AssignmentListInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\GroupByInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Having;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\Assign;
use IfCastle\AQL\Dsl\Sql\Query\Expression\OrderByInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\ValueListInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Dsl\Sql\Query\WithInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumnInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;

final class ContextHelper
{
    public static function resolveContextName(NodeInterface $node): string
    {
        $result                     = '';

        NodeHelper::traverseUpUntil($node, function (NodeInterface $node) use (&$result): ?bool {
            switch (true) {

                // Check if the node is an instance of the following classes
                // We need to check WithInterface first because it is a child class of QueryInterface
                case $node instanceof WithInterface:
                    $result         = NodeContextInterface::CONTEXT_CTE;
                    return false;

                case $node instanceof QueryInterface:

                    return false;

                case $node instanceof Where || $node instanceof Having:
                    $result         = NodeContextInterface::CONTEXT_FILTER;
                    return true;

                case $node instanceof JoinConditionsInterface:
                    $result         = NodeContextInterface::CONTEXT_JOIN_CONDITIONS;
                    return true;

                case $node instanceof TupleColumnInterface || $node instanceof TupleInterface:
                    $result         = NodeContextInterface::CONTEXT_TUPLE;
                    return true;

                case $node instanceof Assign || $node instanceof AssignmentListInterface || $node instanceof ValueListInterface:
                    $result         = NodeContextInterface::CONTEXT_ASSIGN;
                    return true;

                case $node instanceof JoinInterface:
                    $result         = NodeContextInterface::CONTEXT_JOIN;
                    return true;

                case $node instanceof RelationInterface:
                    $result         = NodeContextInterface::CONTEXT_RELATIONS;
                    return true;

                case $node instanceof OrderByInterface:
                    $result         = NodeContextInterface::CONTEXT_ORDER_BY;
                    return true;

                case $node instanceof GroupByInterface:
                    $result         = NodeContextInterface::CONTEXT_GROUP_BY;
                    return true;
            };

            return null;
        });

        return $result;
    }
}
