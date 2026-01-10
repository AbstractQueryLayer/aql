<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Scope;

use IfCastle\AQL\Aspects\AccessControl\AccessByGroups\EntityAccess;
use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\QueryOptionInterface;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\SubjectInterface;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\Tuple;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumn;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\Scope\Exceptions\EntityAccessNotAllowed;
use IfCastle\AQL\Executor\Scope\Exceptions\FunctionAccessNotAllowed;
use IfCastle\AQL\Executor\Scope\Exceptions\PropertyAccessNotAllowed;
use IfCastle\Exceptions\RequiredValueEmpty;

/**
 * A class that calculates the availability of an entity and its properties based on aspect options.
 */
final readonly class EntityAccessByScopeRule implements EntityScopeRulesInterface
{
    public function __construct(private string $scopeName) {}

    /**
     * @throws RequiredValueEmpty
     * @throws EntityAccessNotAllowed
     */
    #[\Override]
    public function handleQuery(BasicQueryInterface $query, NodeContextInterface $context): void
    {
        if ($query instanceof QueryInterface && $query->getTuple()?->isDefaultColumns()) {
            $this->handleDefaultColumns($query, $context);
        }
    }

    /**
     * @throws EntityAccessNotAllowed|RequiredValueEmpty
     */
    protected function handleDefaultColumns(QueryInterface $query, NodeContextInterface $context): void
    {
        $columns                    = [];

        foreach ($context->getMainEntity()->getProperties() as $property) {

            // Use only properties that are able to be result
            // and are not virtual
            if ($property->isAbleResult() && false === $property->isVirtual() && $property->hasAccess($this->scopeName)) {
                $columns[]          = new TupleColumn(new Column($property->getName()));
            }
        }

        if ($columns === []) {
            throw new EntityAccessNotAllowed(
                $context->getMainEntity()->getEntityName(),
                'No columns available for query',
                'no_columns_available',
                $this->scopeName
            );
        }

        $query->getTuple()->setSubstitution(new Tuple(...$columns));
    }

    /**
     * @throws PropertyAccessNotAllowed|EntityAccessNotAllowed
     */
    #[\Override]
    public function handleColumn(ColumnInterface $column, NodeContextInterface $context): void
    {
        $entity = $column->getEntityName() === null || $column->getEntityName() === ''
                ? $context->getCurrentEntity() : $context->getEntity($column->getEntityName());

        $this->checkEntityAccess($entity);

        $property                   = $entity->getProperty($column->getColumnName());

        if (false === $property->hasAccess($this->scopeName)) {
            throw new PropertyAccessNotAllowed($entity->getEntityName(), $property->getName(), $this->scopeName);
        }
    }

    /**
     * @throws FunctionAccessNotAllowed
     */
    #[\Override]
    public function handleFunction(FunctionReferenceInterface $function, NodeContextInterface $context): void
    {
        $functionHandler            = $context->resolveFunction($function, $context);

        if ($functionHandler === null || $functionHandler->hasAccess($this->scopeName)) {
            throw new FunctionAccessNotAllowed($function->getFunctionName(), $this->scopeName);
        }
    }

    #[\Override]
    public function handleOption(QueryOptionInterface $queryOption, NodeContextInterface $context): void
    {
        // TODO: Implement handleOption() method.
    }

    /**
     * @throws EntityAccessNotAllowed
     */
    #[\Override]
    public function handleSubject(SubjectInterface $subject, NodeContextInterface $context): void
    {
        $this->checkEntityAccess($context->getEntity($subject->getSubjectName()));
    }

    #[\Override]
    public function handleJoin(JoinInterface $join, NodeContextInterface $context): void {}

    /**
     * @throws EntityAccessNotAllowed
     */
    protected function checkEntityAccess(EntityInterface $entity): void
    {
        $aspect                     = $entity->findEntityAspect(EntityAccess::NAME);

        if ($aspect instanceof EntityAccess === false) {
            throw new EntityAccessNotAllowed($entity->getEntityName(), 'aspect', '', $this->scopeName);
        }

        if (false === $aspect->isAllowed($this->scopeName)) {
            throw new EntityAccessNotAllowed($entity->getEntityName(), 'scope', '', $this->scopeName);
        }
    }
}
