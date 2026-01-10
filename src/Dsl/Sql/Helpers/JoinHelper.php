<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Helpers;

use IfCastle\AQL\Dsl\Node\Exceptions\NodeException;
use IfCastle\AQL\Dsl\Node\NodeRecursiveIterator;
use IfCastle\AQL\Dsl\Node\NodeRecursiveIteratorByJoin;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Join;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Subject;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;

final class JoinHelper
{
    /**
     * @throws NodeException
     */
    public static function resolveFrom(QueryInterface $query): JoinInterface
    {
        $from                       = $query->getFrom()->resolveSubstitution();

        if ($from instanceof JoinInterface === false) {
            throw new NodeException([
                'template'          => 'Wrong FROM node, expected JoinInterface, got {type}',
                'storage'           => $query->getQueryStorage(),
                'query'             => $query->getAql(),
                'type'              => \get_debug_type($from),
            ]);
        }

        return $from;
    }

    public static function findJoinByEntityName(QueryInterface $query, string $entityName): JoinInterface|null
    {
        $from                       = $query->getFrom();

        if ($from === null) {
            return null;
        }

        if ($from->getSubject()->getSubjectName() === $entityName) {
            return $from;
        }

        foreach (new \RecursiveIteratorIterator(new NodeRecursiveIteratorByJoin($from), \RecursiveIteratorIterator::SELF_FIRST) as $join) {
            /* @var $join JoinInterface */
            if ($join->getSubject()->getSubjectName() === $entityName) {
                return $join;
            }
        }

        return null;
    }

    public static function findOrCreateJoinByEntityName(QueryInterface $query, string $entityName): JoinInterface
    {
        $join                       = self::findJoinByEntityName($query, $entityName);

        if ($join === null) {
            $join                   = new Join(joinType: '', subject: new Subject($entityName));
            $query->getFrom()->addJoin($join);
        }

        return $join;
    }

    /**
     * @throws NodeException
     */
    public static function toJoinList(QueryInterface $query): array
    {
        $from                       = self::resolveFrom($query);

        $list                       = [$from];

        foreach (new \RecursiveIteratorIterator(new NodeRecursiveIterator($from, resolveSubstitution: true)) as $join) {
            if ($join instanceof JoinInterface) {
                $list[]             = $join;
            }
        }

        return $list;
    }

    /**
     * @throws NodeException
     */
    public static function toPlainAliasJoinList(QueryInterface $query): array
    {
        $from                       = self::resolveFrom($query);

        $list                       = [$from->getAlias() => $from];

        foreach (new \RecursiveIteratorIterator(new NodeRecursiveIterator($from, resolveSubstitution: true)) as $join) {
            if ($join instanceof JoinInterface) {
                $list[$join->getAlias()] = $join;
            }
        }

        return $list;
    }
}
