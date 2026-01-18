<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\AssignmentListInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\GroupByInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Having;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\Assign;
use IfCastle\AQL\Dsl\Sql\Query\Expression\OrderByInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\ValueListInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;
use IfCastle\AQL\Dsl\Sql\Query\UnionInterface;
use IfCastle\AQL\Dsl\Sql\Query\WithInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumnInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleInterface;

class NodeHelper
{
    public static function traverseUpUntil(NodeInterface $node, callable $matcher): NodeInterface|null
    {
        $current                    = $node;

        while ($current !== null) {

            $result                 = $matcher($current);
            if ($result === true) {
                return $current;
            }

            if ($result === false) {
                return null;
            }

            $current                = $current->getParentNode();
        }

        return null;
    }

    /**
     * The method returns the parent Node, which is the "basic" one for the query.
     * For example, for the `TupleColumn` node, the basic one will be `Tuple`.
     * For query conditions, it will be the `Where` node, and so on.
     */
    public static function ascendToQueryBasicNode(NodeInterface $node): NodeInterface|null
    {
        $prevNode                   = null;

        $queryNode                  = self::traverseUpUntil($node, function (NodeInterface $node) use (&$prevNode): ?true {
            if ($node instanceof QueryInterface) {
                return true;
            }

            $prevNode               = $node;
            return null;

        });

        if ($queryNode !== null) {
            return $prevNode;
        }

        return null;
    }

    /**
     * The method returns the expression node that is independent of the base query node.
     * For example, for a column inside a Tuple, this would be the TupleColumn expression.
     *
     * @throws TransformationException
     */
    public static function findBasicExpressionByNode(NodeInterface $node): NodeInterface|null
    {
        $path                       = [];

        $queryNode                  = self::traverseUpUntil($node, function (NodeInterface $node) use (&$path): ?true {
            if ($node instanceof QueryInterface) {
                return true;
            }

            $path[]             = $node;
            return null;

        });

        if ($queryNode === null || $path === []) {
            return null;
        }

        $basicNode                  = $path[0];

        if ($basicNode instanceof TupleInterface) {

            // Get TupleColumn
            // $basicNode
            //    |
            //    +-- NodeList
            //          |
            //          +-- TupleColumn
            if (\count($path) < 3) {
                return null;
            }

            if (false === $path[2] instanceof TupleColumnInterface) {
                throw new TransformationException([
                    'template'          => 'Invalid TupleColumn node {node} in Tuple node {aql}',
                    'node'              => $path[2]::class,
                    'aql'               => $basicNode->getAql(),
                ]);
            }

            return $path[2];
        } elseif (\count($path) >= 2) {
            return $path[1];
        }

        return null;

    }

    public static function inTuple(NodeInterface $node): bool
    {
        return self::traverseUpUntil($node, fn(NodeInterface $node) => match (true) {
            $node instanceof TupleColumnInterface, $node instanceof TupleInterface => true,
            $node instanceof QueryInterface, $node instanceof Where, $node instanceof RelationInterface => false,
            default => null
        }) !== null;
    }

    public static function inJoin(NodeInterface $node): bool
    {
        return self::traverseUpUntil($node, fn(NodeInterface $node) => match (true) {
            $node instanceof JoinInterface  => true,
            $node instanceof QueryInterface => false,
            default => null
        }) !== null;
    }

    public static function inFilter(NodeInterface $node): bool
    {
        return self::traverseUpUntil($node, fn(NodeInterface $node) => match (true) {
            $node instanceof Where, $node instanceof Having => true,
            $node instanceof QueryInterface                 => false,
            default                                         => null
        }) !== null;
    }

    public static function inWhere(NodeInterface $node): bool
    {
        return self::traverseUpUntil($node, fn(NodeInterface $node) => match (true) {
            $node instanceof Where                           => true,
            $node instanceof QueryInterface, $node instanceof Having => false,
            default                                          => null
        }) !== null;
    }

    public static function inHaving(NodeInterface $node): bool
    {
        return self::traverseUpUntil($node, fn(NodeInterface $node) => match (true) {
            $node instanceof Having                          => true,
            $node instanceof QueryInterface, $node instanceof Where => false,
            default                                          => null
        }) !== null;
    }

    public static function inAssign(NodeInterface $node): bool
    {
        return self::traverseUpUntil($node, fn(NodeInterface $node) => match (true) {
            $node instanceof ValueListInterface, $node instanceof AssignmentListInterface, $node instanceof Assign => true,
            $node instanceof QueryInterface     => false,
            default                             => null
        }) !== null;
    }

    public static function inOrderBy(NodeInterface $node): bool
    {
        return self::traverseUpUntil($node, fn(NodeInterface $node) => match (true) {
            $node instanceof OrderByInterface => true,
            $node instanceof QueryInterface => false,
            default                         => null
        }) !== null;
    }

    public static function inGroupBy(NodeInterface $node): bool
    {
        return self::traverseUpUntil($node, fn(NodeInterface $node) => match (true) {
            $node instanceof GroupByInterface   => true,
            $node instanceof QueryInterface     => false,
            default                             => null
        }) !== null;
    }

    /**
     * Returns TRUE if context is inside RELATIONS
     * The method counts parent contexts.
     * Contexts: QUERY or SUBQUERY leads to returns FALSE.
     */
    public static function inRelation(NodeInterface $node): bool
    {
        return self::traverseUpUntil($node, fn(NodeInterface $node) => match (true) {
            $node instanceof RelationInterface => true,
            $node instanceof QueryInterface    => false,
            default                            => null
        }) !== null;
    }

    public static function inSubquery(NodeInterface $node): bool
    {
        return self::traverseUpUntil($node, fn(NodeInterface $node) => match (true) {
            $node instanceof SubqueryInterface => true,
            $node instanceof QueryInterface => false,
            default                         => null
        }) !== null;
    }

    public static function inFromSelect(NodeInterface $node): bool
    {
        return self::traverseUpUntil($node, fn(NodeInterface $node) => match (true) {
            $node instanceof SubqueryInterface && $node->isFromSelect() => true,
            $node instanceof QueryInterface => false,
            default                         => null
        }) !== null;
    }

    public static function inUnion(NodeInterface $node): bool
    {
        return self::traverseUpUntil($node, fn(NodeInterface $node) => match (true) {
            $node instanceof UnionInterface => true,
            $node instanceof QueryInterface => false,
            default => null
        }) !== null;
    }

    public static function inCte(NodeInterface $node): bool
    {
        return self::traverseUpUntil($node, fn(NodeInterface $node) => match (true) {
            $node instanceof WithInterface => true,
            $node instanceof QueryInterface && false === $node instanceof SubqueryInterface => false,
            default => null
        }) !== null;
    }

    public static function inCteOrSubquery(NodeInterface $node): bool
    {
        return self::traverseUpUntil($node, fn(NodeInterface $node) => match (true) {
            $node instanceof WithInterface, $node instanceof SubqueryInterface => true,
            $node instanceof QueryInterface => false,
            default => null
        }) !== null;
    }

    public static function findCTENode(NodeInterface $node): WithInterface|null
    {
        $result = self::traverseUpUntil($node, fn(NodeInterface $node) => match (true) {
            $node instanceof WithInterface => $node,
            default => null
        });

        return $result instanceof WithInterface ? $result : null;
    }

    /**
     * Return first child node of WHERE node from current node with current context and iterator.
     */
    public static function findFirstChildOfWhereNode(NodeInterface $node): NodeInterface|null
    {
        $current                    = $node;
        $prevNode                   = null;

        while ($current !== null) {

            if ($current instanceof Where || $current instanceof QueryInterface) {
                break;
            }

            $prevNode               = $current;
            $current                = $current->getParentNode();
        }

        return $prevNode;
    }

    public static function findChildByType(NodeInterface $node, string $type): NodeInterface|null
    {
        foreach (new \RecursiveIteratorIterator(new NodeRecursiveIterator($node)) as $child) {
            if (\is_subclass_of($child, $type)) {
                return $child;
            }
        }

        return null;
    }

    public static function findColumnByEntity(NodeInterface $node, string $entityName): ColumnInterface|null
    {
        foreach (new \RecursiveIteratorIterator(new NodeRecursiveIterator($node)) as $column) {
            if ($column instanceof ColumnInterface && $column->getEntityName() === $entityName) {
                return $column;
            }
        }

        return null;
    }

    public static function getNearestAql(NodeInterface $node, int $deep = 3): string
    {
        $count                      = 0;
        $last                       = null;

        $founded                    = self::traverseUpUntil($node, function (NodeInterface $node) use (&$count, &$last, $deep): ?true {

            $last                   = $node;

            if ($count > $deep) {
                return true;
            }

            $count++;
            return null;

        }) ?? $last ?? $node;

        return $founded instanceof NodeInterface ? $founded->getAql() : '';
    }
}
