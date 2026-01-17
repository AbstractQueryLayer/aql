<?php

declare(strict_types=1);

namespace IfCastle\AQL\SQLite\Ddl\Generator;

use IfCastle\AQL\Dsl\Ddl\ColumnDefinitionInterface;
use IfCastle\AQL\Entity\Property\PropertyInterface;
use IfCastle\AQL\Generator\Ddl\PropertyToColumnAbstract;

class PropertyToColumn extends PropertyToColumnAbstract
{
    /**
     * @throws \ErrorException
     * @see https://www.sqlite.org/datatype3.html
     */
    #[\Override]
    protected function defineColumnType(): array
    {
        return match ($this->property->getType()) {
            PropertyInterface::T_BOOLEAN                                                         => ['TINYINT', 3, null],
            PropertyInterface::T_STRING                                                          => ['VARCHAR', $this->property->getMaxLength() ?? 255, null],
            PropertyInterface::T_ENUM                                                            => ['VARCHAR', null, null],
            PropertyInterface::T_INT                                                             => ['INTEGER', $this->property->getMaxLength(), null],
            PropertyInterface::T_BIG_INT                                                         => $this->property->isAutoIncrement()
                ? ['INTEGER', null, null]
                : ['BIGINT', $this->property->getMaxLength(), null],
            PropertyInterface::T_FLOAT                                                           => ['FLOAT', $this->property->getMaxLength(), null],
            PropertyInterface::T_UUID                                                            => ['CHAR', 36, null],
            PropertyInterface::T_ULID                                                            => ['CHAR', 26, null],
            PropertyInterface::T_DATE                                                            => ['CHAR', null, null],
            PropertyInterface::T_YEAR, PropertyInterface::T_TIME, PropertyInterface::T_TIMESTAMP => ['INTEGER', null, null],
            PropertyInterface::T_DATETIME                                                        => ['DATETIME', null, null],
            PropertyInterface::T_JSON,
            PropertyInterface::T_LIST,
            PropertyInterface::T_TEXT,
            PropertyInterface::T_OBJECT                                                          => ['TEXT', null, null],

            default                                                                              => throw new \ErrorException(
                'Unknown property type: ' . $this->property->getType()
            ),
        };
    }

    #[\Override]
    protected function generateAfter(ColumnDefinitionInterface|array $column): void
    {
        if ($column instanceof ColumnDefinitionInterface) {
            $column->resetVariants()->resetUnsigned();
            return;
        }

        foreach ($column as $item) {
            $item->resetVariants();
        }
    }
}
