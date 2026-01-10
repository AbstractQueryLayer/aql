<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Helpers;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Node\NodeRecursiveIterator;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Executor\Exceptions\QueryException;

final class ConsistencyHelper
{
    /**
     * @throws QueryException
     */
    public static function checkNode(NodeInterface $node, EntityInterface $defaultEntity, EntityFactoryInterface $entityFactory): void
    {
        $entities                   = [$defaultEntity];

        foreach (new \RecursiveIteratorIterator(new NodeRecursiveIterator($node)) as $column) {

            if ($column instanceof ColumnInterface === false) {
                continue;
            }

            $entityName             = $column->getEntityName();
            $entity                 = $entityName !== null ? $entityFactory->getEntity($entityName) : $defaultEntity;

            if (\in_array($entity, $entities, true)) {
                continue;
            }

            foreach ($entities as $existingEntity) {
                if ($entity->isConsistentRelationWith($existingEntity) === false) {
                    throw new QueryException([
                        'template'      => 'Inconsistent relation between {entity1} and {entity2} for expression {expression}',
                        'entity1'       => $entity->getEntityName(),
                        'entity2'       => $existingEntity->getEntityName(),
                        'expression'    => $node->getAql(),
                    ]);
                }
            }

            $entities[]         = $entity;
        }
    }
}
