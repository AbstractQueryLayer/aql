<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity;

use IfCastle\AOP\AspectDescriptorInterface;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\AQL\Entity\Functions\FunctionInterface;
use IfCastle\AQL\Entity\Key\KeyInterface;
use IfCastle\AQL\Entity\PostAction\PostActionAwareInterface;
use IfCastle\AQL\Entity\Property\PropertyInterface;
use IfCastle\DI\ConfigInterface;

interface EntityInterface extends PostActionAwareInterface
{
    /**
     * Contains entity name.
     */
    public const string ENTITY      = '';

    public static function entity(): string;

    public function getEntityName(): string;

    public function getTypicalEntityName(): string;

    public function getSubject(): string;

    public function getStorageName(): ?string;

    public function getOptions(): ConfigInterface;

    public function getInheritedEntity(): ?string;

    /**
     * Returns entity aspects.
     *
     * @return AspectDescriptorInterface[]
     */
    public function getEntityAspects(): array;


    public function findEntityAspect(string $aspectName): ?AspectDescriptorInterface;

    /**
     * @return array<string, PropertyInterface>
     */
    public function getProperties(): array;

    /**
     * @return array<string, PropertyInterface>
     */
    public function getTypicalProperties(): array;

    public function findAutoIncrement(): ?PropertyInterface;

    public function getKeys(): array;

    /**
     * @return FunctionInterface[]
     */
    public function getFunctions(): array;

    public function findFunction(FunctionReferenceInterface $functionReference): ?FunctionInterface;

    public function getRelations(): array;

    public function getModifiers(): array;

    public function getConstraints(): array;

    public function getActions(): array;

    public function getPrimaryKey(): ?KeyInterface;

    public function getTitleProperty(): string;

    public function getProperty(string $propertyName, bool $isThrow = true): ?PropertyInterface;

    public function getTypicalProperty(string $name, bool $isThrow = true): ?PropertyInterface;

    public function getDefaultColumns(): array;

    /**
     * @throws EntityDescriptorException
     */
    public function getRelation(string $toEntity): RelationInterface;


    public function findRelation(string $toEntity): ?RelationInterface;


    public function resolveRelation(EntityInterface $toEntity): RelationInterface;

    public function reverseRelation(?RelationInterface $relation = null): ?RelationInterface;

    public function isConsistentRelationWith(EntityInterface $withEntity): bool|null;
}
