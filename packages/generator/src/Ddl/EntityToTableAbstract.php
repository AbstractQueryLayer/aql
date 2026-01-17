<?php

declare(strict_types=1);

namespace IfCastle\AQL\Generator\Ddl;

use IfCastle\AQL\Dsl\Ddl\ColumnDefinitionInterface;
use IfCastle\AQL\Dsl\Ddl\ConstraintDefinition;
use IfCastle\AQL\Dsl\Ddl\Table;
use IfCastle\AQL\Dsl\Ddl\TableInterface;
use IfCastle\AQL\Entity\Builder\NamingStrategy\NamingStrategyInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Entity\Property\PropertyInterface;
use IfCastle\AQL\Entity\Relation\BuildingRequiredRelationInterface;
use IfCastle\AQL\Entity\Relation\DirectRelationInterface;
use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ContainerInterface;

/**
 * ## EntityToTable.
 *
 * A generator that turns an entity into a DDL structure.
 */
abstract class EntityToTableAbstract implements EntityToTableInterface, AutoResolverInterface
{
    public static function extractDocFromComment(string|bool $comment): string
    {
        if (\is_bool($comment)) {
            return '';
        }

        return \trim((string) \preg_replace(
            '%(\r?\n(?! \* ?@))?^(/\*\*\r?\n| \*/| \* ?)%m', "\n", $comment
        ));
    }

    /**
     * @throws \ReflectionException
     */
    public static function extractDocFromClass($object): string
    {
        return static::extractDocFromComment((new \ReflectionClass($object))->getDocComment());
    }

    protected ?Table $table         = null;

    protected EntityFactoryInterface $entityFactory;

    protected NamingStrategyInterface $namingStrategy;

    public function __construct(protected EntityInterface $entity) {}

    #[\Override]
    public function resolveDependencies(ContainerInterface $container): void
    {
        $this->entityFactory        = $container->resolveDependency(EntityFactoryInterface::class);
        $this->namingStrategy       = $container->resolveDependency(NamingStrategyInterface::class);
    }

    /**
     * @throws \ErrorException
     */
    #[\Override]
    public function generate(): TableInterface
    {
        $this->table                = new Table($this->entity->getSubject(), []);

        $this->generateBefore();

        if ($this->entity instanceof EntityToTableBeforeInterface) {
            $this->entity->handleEntityToTableBefore($this);
        }

        $this->generateColumns();
        $this->generateIndexes();
        $this->generateConstraints();

        if ($this->entity instanceof EntityToTableAfterInterface) {
            $this->entity->handleEntityToTableAfter($this);
        }

        $this->generateComment();

        $this->generateAfter();

        return $this->table;
    }

    protected function generateBefore(): void {}

    protected function generateAfter(): void {}

    /**
     * @throws \ErrorException
     */
    protected function generateColumns(): void
    {
        $columns                    = [];

        foreach ($this->entity->getProperties() as $property) {

            $column                 = null;

            if ($property instanceof GenerateColumnInterface) {
                $column             = $property->generateColumnDefinition();
            } elseif ($property->isVirtual() === false && $property->getInheritedFrom() === null) {
                $column             = $this->propertyToColumn($property);
            }

            if (\is_array($column)) {
                $columns            = \array_merge($columns, $column);
            } elseif ($column !== null) {
                $columns[]          = $column;
            }
        }

        $this->table->setColumns($columns);
    }

    abstract protected function propertyToColumn(PropertyInterface $property): ColumnDefinitionInterface|array;

    protected function generateIndexes(): void {}

    protected function generateConstraints(): void
    {
        foreach ($this->entity->getRelations() as $relation) {

            if ($relation instanceof DirectRelationInterface === false) {
                continue;
            }

            if ($relation instanceof BuildingRequiredRelationInterface) {
                $relation->buildRelations($this->entityFactory);
            }

            $toEntity               = $this->entityFactory->getEntity($relation->getRightEntityName());
            $leftColumns            = \array_map(
                fn(string $propertyName) => $this->entity->getProperty($propertyName)->getFieldName(),
                $relation->getLeftKey()->getKeyColumns()
            );

            $foreignColumns         = \array_map(
                static fn(string $propertyName) => $toEntity->getProperty($propertyName)->getFieldName(),
                $relation->getRightKey()->getKeyColumns()
            );

            $constraintName         = $this->namingStrategy->generateConstraintName($this->entity, $toEntity, $relation->getLeftKey()->getKeyName());
            $referenceActions       = [];

            $constraint             = new ConstraintDefinition(
                $leftColumns,
                $toEntity->getSubject(),
                $foreignColumns,
                $referenceActions,
                $constraintName
            );

            $this->table->addConstraint($constraint);
        }
    }

    protected function generateComment(): void
    {
        $comment                    = \trim(static::extractDocFromClass($this->entity));

        if ($comment !== '' && $comment !== '0') {
            $comment                = \str_replace(["\n", '  '], ' ', $comment);
            $this->table->setComment($comment);
        }
    }
}
