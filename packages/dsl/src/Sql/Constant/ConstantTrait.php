<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Constant;

use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;

trait ConstantTrait
{
    abstract protected function quote(mixed $name): string;

    /**
     * @throws TransformationException
     */
    protected function valueToSql(mixed $value): string
    {
        if (\is_null($value)) {
            return 'NULL';
        }

        if (\is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (\is_scalar($value)) {
            return $this->quote($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return $this->quote($value->format('Y-m-d H:i:s'));
        }

        throw new TransformationException([
            'template'          => 'Cannot normalize value of type {type} for {node}',
            'type'              => \get_debug_type($value),
            'node'              => $this::class,
        ]);

    }

    /**
     * @throws TransformationException
     */
    protected function arrayToSql(array $value): string
    {
        $sql                        = [];

        foreach ($value as $item) {
            $result                 = $this->valueToSql($item);

            if ($result !== '') {
                $sql[]              = $result;
            }
        }

        if ($sql === []) {
            return '';
        }

        return '(' . \implode(',', $sql) . ')';
    }

    protected function constantToAQL(mixed $value): string
    {
        if (\is_null($value)) {
            return 'NULL';
        }

        if (\is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        if (\is_string($value)) {
            return '"' . \addslashes($value) . '"';
        }

        return \get_debug_type($value);

    }
}
