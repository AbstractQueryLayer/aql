<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity;

use IfCastle\AOP\AspectDescriptorInterface;
use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\AQL\Entity\Functions\FunctionInterface;
use IfCastle\AQL\Entity\Key\KeyInterface;
use IfCastle\AQL\Entity\Property\PropertyInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\FunctionHandlerInterface;
use IfCastle\AQL\Executor\QueryExecutorInterface;
use IfCastle\AQL\Executor\QueryExecutorResolverInterface;
use IfCastle\DI\ConfigInterface;

/**
 * A second occurrence of an entity in one query: the rename operator ρ of relational algebra.
 *
 * The executor keys SQL aliases, joins and relation conditions by entity name, so an entity can occur once per
 * query. An AliasEntity gives a further occurrence its own name (the query alias, first letter upper-cased) and
 * otherwise behaves as the original: same table, properties and keys, and the original's relations with this
 * name on their left side. It lives only in the query context that registered it.
 */
final readonly class AliasEntity implements EntityInterface, FunctionHandlerInterface, QueryExecutorResolverInterface
{
    public function __construct(private string $name, private EntityInterface $original) {}

    public function getOriginalEntity(): EntityInterface
    {
        return $this->original;
    }

    #[\Override]
    public static function entity(): string
    {
        return '';
    }

    #[\Override]
    public function getEntityName(): string
    {
        return $this->name;
    }

    #[\Override]
    public function getTypicalEntityName(): string
    {
        return $this->original->getTypicalEntityName();
    }

    #[\Override]
    public function getSubject(): string
    {
        return $this->original->getSubject();
    }

    #[\Override]
    public function getStorageName(): ?string
    {
        return $this->original->getStorageName();
    }

    #[\Override]
    public function getOptions(): ConfigInterface
    {
        return $this->original->getOptions();
    }

    #[\Override]
    public function getInheritedEntity(): ?string
    {
        return $this->original->getInheritedEntity();
    }

    #[\Override]
    public function getEntityAspects(): array
    {
        return $this->original->getEntityAspects();
    }

    #[\Override]
    public function findEntityAspect(string $aspectName): ?AspectDescriptorInterface
    {
        return $this->original->findEntityAspect($aspectName);
    }

    #[\Override]
    public function getProperties(): array
    {
        return $this->original->getProperties();
    }

    #[\Override]
    public function getTypicalProperties(): array
    {
        return $this->original->getTypicalProperties();
    }

    #[\Override]
    public function findAutoIncrement(): ?PropertyInterface
    {
        return $this->original->findAutoIncrement();
    }

    #[\Override]
    public function getKeys(): array
    {
        return $this->original->getKeys();
    }

    #[\Override]
    public function getFunctions(): array
    {
        return $this->original->getFunctions();
    }

    #[\Override]
    public function findFunction(FunctionReferenceInterface $functionReference): ?FunctionInterface
    {
        return $this->original->findFunction($functionReference);
    }

    #[\Override]
    public function getRelations(): array
    {
        return \array_map($this->rename(...), $this->original->getRelations());
    }

    #[\Override]
    public function getModifiers(): array
    {
        return $this->original->getModifiers();
    }

    #[\Override]
    public function getConstraints(): array
    {
        return $this->original->getConstraints();
    }

    #[\Override]
    public function getActions(): array
    {
        return $this->original->getActions();
    }

    #[\Override]
    public function getPrimaryKey(): ?KeyInterface
    {
        return $this->original->getPrimaryKey();
    }

    #[\Override]
    public function getTitleProperty(): string
    {
        return $this->original->getTitleProperty();
    }

    #[\Override]
    public function getProperty(string $propertyName, bool $isThrow = true): ?PropertyInterface
    {
        return $this->original->getProperty($propertyName, $isThrow);
    }

    #[\Override]
    public function getTypicalProperty(string $name, bool $isThrow = true): ?PropertyInterface
    {
        return $this->original->getTypicalProperty($name, $isThrow);
    }

    #[\Override]
    public function getDefaultColumns(): array
    {
        return $this->original->getDefaultColumns();
    }

    #[\Override]
    public function getRelation(string $toEntity): RelationInterface
    {
        return $this->rename($this->original->getRelation($toEntity));
    }

    #[\Override]
    public function findRelation(string $toEntity): ?RelationInterface
    {
        $relation                   = $this->original->findRelation($toEntity);

        return $relation === null ? null : $this->rename($relation);
    }

    /**
     * @throws EntityDescriptorException
     */
    #[\Override]
    public function resolveRelation(EntityInterface $toEntity): RelationInterface
    {
        return $this->rename($this->original->resolveRelation($toEntity));
    }

    #[\Override]
    public function reverseRelation(?RelationInterface $relation = null): ?RelationInterface
    {
        return $relation?->reverseRelation();
    }

    #[\Override]
    public function isConsistentRelationWith(EntityInterface $withEntity): bool|null
    {
        return $this->original->isConsistentRelationWith($withEntity);
    }

    #[\Override]
    public function getPostActions(): array
    {
        return $this->original->getPostActions();
    }

    #[\Override]
    public function handleFunction(FunctionReferenceInterface $function, NodeContextInterface $context): void
    {
        if ($this->original instanceof FunctionHandlerInterface) {
            $this->original->handleFunction($function, $context);
        }
    }

    #[\Override]
    public function resolveQueryExecutor(BasicQueryInterface $basicQuery, ?EntityInterface $entity = null): ?QueryExecutorInterface
    {
        return $this->original instanceof QueryExecutorResolverInterface
            ? $this->original->resolveQueryExecutor($basicQuery, $entity)
            : null;
    }

    private function rename(RelationInterface $relation): RelationInterface
    {
        return $relation->cloneWithSubjects(leftEntityName: $this->name);
    }
}
