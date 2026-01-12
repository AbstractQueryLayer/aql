<?php

declare(strict_types=1);

namespace IfCastle\AQL\Generator\Ddl;

use IfCastle\AQL\Dsl\Ddl\ColumnDefinition;
use IfCastle\AQL\Dsl\Ddl\ColumnDefinitionInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Entity\Property\PropertyEnumInterface;
use IfCastle\AQL\Entity\Property\PropertyInterface;

abstract class PropertyToColumnAbstract
{
    public function __construct(
        protected EntityInterface        $entity,
        protected EntityFactoryInterface $entityFactory,
        protected PropertyInterface $property
    ) {}

    /**
     * @throws \ErrorException
     */
    public function generate(): ColumnDefinitionInterface|array
    {
        if ($this->property instanceof GenerateColumnInterface) {

            $column                 = $this->property->generateColumnDefinition();

            $this->generateAfter($column);

            return $column;
        }

        $columnName                 = $this->property->getFieldName();

        [$columnType, $maximumDisplayWidth, $digitsNumber] = $this->defineColumnType();

        $column                     = new ColumnDefinition(
            $columnName,
            $columnType,
            $maximumDisplayWidth,
            $digitsNumber,
            $this->property->isUnsigned(),
            $this->property->isNullable(),
            false,
            $this->property->isAutoIncrement()
        );

        if ($this->property instanceof PropertyEnumInterface) {

            $variants               = $this->property->getVariants();

            if (\array_key_first($variants) !== 0) {
                $variants           = \array_keys($variants);
            }

            $column->setVariants($variants);
        }

        $column->setDefaultValue($this->property->getDefaultValue());

        if ($this->property instanceof GenerateColumnAfterInterface) {
            return $this->property->generateColumnDefinitionAfter($column);
        }

        $this->generateAfter($column);

        return $column;
    }

    protected function generateAfter(ColumnDefinitionInterface|array $column): void {}

    abstract protected function defineColumnType(): array;
}
