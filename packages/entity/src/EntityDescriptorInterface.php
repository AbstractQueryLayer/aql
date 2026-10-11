<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity;

use IfCastle\AOP\AspectDescriptorInterface;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\Builder\NamingStrategy\NamingStrategyAwareInterface;
use IfCastle\AQL\Entity\Constraints\ConstraintInterface;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\AQL\Entity\Functions\FunctionInterface;
use IfCastle\AQL\Entity\Key\KeyInterface;
use IfCastle\AQL\Entity\Modifiers\ModifierInterface;
use IfCastle\AQL\Entity\PostAction\PostActionDescriberInterface;
use IfCastle\AQL\Entity\Property\PropertyInterface;
use IfCastle\AQL\Executor\QueryExecutorInterface;
use IfCastle\AQL\Executor\QueryHandlerInterface;
use IfCastle\AQL\Executor\QueryPostHandlerInterface;
use IfCastle\DI\ConfigMutableInterface;

interface EntityDescriptorInterface extends NamingStrategyAwareInterface, PostActionDescriberInterface
{
    public function getBuildPlan(): ?EntityBuildPlanInterface;

    /**
     * Implements the assembly process of an entity specification.
     *
     *
     */
    public function build(bool $isRaw = false): void;

    /**
     * The method will return TRUE if the entity specification has already been built.
     */
    public function wasBuilt(): bool;

    public function getOptionsMutable(): ConfigMutableInterface;

    public function setTypicalEntityName(string $typicalEntityName): static;

    /**
     * Describes an aspect of an entity.
     * Attempting to define the same aspect twice throws an exception if the parameter $isRedefine is not specified.
     *
     *
     * @return   $this
     * @throws  EntityDescriptorException
     */
    public function describeAspect(AspectDescriptorInterface $aspectDescriptor, bool $isRedefine = false): static;

    /**
     * Describes a property of an entity
     * When trying to define the same property twice, it throws an exception if the parameter $isRedefine is not specified.
     *
     *
     * @return   $this
     * @throws  EntityDescriptorException
     */
    public function describeProperty(PropertyInterface $property, bool $isRedefine = false): static;

    /**
     * Describes an entity property that points to another entity.
     * The property can be NULLABLE or not (default is NOT NULL $isRequired = true).
     * The method causes the Relation section to be automatically populated with the specified relationship.
     *
     * @param string|null  $propertyName the reference column; default: target entity name + its key name
     * @param string|null  $role         relation name; default: the property name without its `Id` suffix
     * @param string       $integrity    RelationInterface::INTEGRITY_*: what guarantees the referenced row
     * @param list<string> $derivedFrom  constraint names an implied relation follows from
     *
     * @return   $this
     */
    public function describeReference(
        string|(EntityInterface & EntityDescriptorInterface) $toEntity,
        string                                             $relationType = RelationInterface::REFERENCE,
        bool                                               $isRequired = true,
        ?string                                             $propertyName = null,
        ?string                                             $role = null,
        string                                             $integrity = RelationInterface::INTEGRITY_DECLARED,
        array                                              $derivedFrom = []
    ): static;

    /**
     * Describes a role over one or more column pairs: a relation named $role from this entity to $toEntity whose
     * join predicate equates every pair (C2, C9, C22 of dev/design/aql-relations.md in the lib repository).
     *
     * The role is joined only by its name (`JOIN alias.role`) and is never required: a query that needs an inner
     * join writes `JOIN`. It is to-one when the target columns contain the target's primary or a unique key, and
     * to-many otherwise. Both entities must be in one storage. The target is built fully first, so the target must
     * not reach back to this entity through roles of its own.
     *
     * @param string                $role        begins with a lowercase letter
     * @param array<string, string> $pairs       own column => target column, in key order; one pair or more
     * @param string                $integrity   INTEGRITY_IMPLIED or INTEGRITY_LOGICAL; a declared composite
     *                                           foreign key is not supported yet
     * @param list<string>          $derivedFrom constraint names, required for INTEGRITY_IMPLIED
     *
     * @return $this
     * @throws EntityDescriptorException when the entity is built, if the name, a column, the target or the integrity
     *                                   does not fit, another role already joins these pairs to the target, or the
     *                                   target's roles lead back to this entity
     */
    public function describeRole(
        string $role,
        string $toEntity,
        array  $pairs,
        string $integrity,
        array  $derivedFrom = []
    ): static;

    /**
     * Describes a cross-reference relationship between entities.
     * please see: https://en.wikipedia.org/wiki/Associative_entity.
     *
     * @return  $this
     */
    public function describeCrossReference(
        string|(EntityInterface&EntityDescriptorInterface) $toEntity,
        ?string                                             $throughEntity   = null,
        bool                                               $isRequired        = true,
        string                                             $relationType    = RelationInterface::REFERENCE
    ): static;

    /**
     * @return   $this
     * @throws  EntityDescriptorException
     */
    public function describeKey(KeyInterface $key, bool $isRedefine = false): static;

    /**
     * @return $this
     * @throws  EntityDescriptorException
     */
    public function describeFunction(FunctionInterface $function, bool $isRedefine = false): static;

    /**
     * @return   $this
     * @throws  EntityDescriptorException
     */
    public function describeModifier(ModifierInterface $modifier, bool $isRedefine = false): static;

    /**
     * @return   $this
     * @throws  EntityDescriptorException
     */
    public function describeRelation(RelationInterface $relation, bool $isRedefine = false): static;

    /**
     *
     * @return   $this
     * @throws  EntityDescriptorException
     */
    public function describeConstraint(ConstraintInterface $constraint): static;

    /**
     * @return $this
     */
    public function addQueryHandler(QueryHandlerInterface $queryHandler): static;

    /**
     * @return $this
     */
    public function addQueryPostHandler(QueryPostHandlerInterface $queryPostHandler): static;

    /**
     *
     * @return   $this
     * @throws  EntityDescriptorException
     */
    public function describeAction(string $action, QueryExecutorInterface $queryExecutor, bool $isRedefine = false): static;
}
