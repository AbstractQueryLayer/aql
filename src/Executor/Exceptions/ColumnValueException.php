<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Exceptions;

use IfCastle\AQL\Entity\Property\PropertyInterface;

class ColumnValueException extends QueryException
{
    public function __construct(string|PropertyInterface $column, string $reason, string $value = '***', string $expected = '')
    {
        if ($column instanceof PropertyInterface) {
            parent::__construct([
                'template'          => 'Property {property} value {value} is invalid ({expected}). {reason}',
                'column'            => $column->getName(),
                'reason'            => $reason,
                'value'             => $value,
                'expected'          => $expected,
                'tags'              => ['property'],
            ]);

            return;
        }

        parent::__construct([
            'template'              => 'Column {column} value {value} is invalid ({expected}). {reason}',
            'column'                => $column,
            'reason'                => $reason,
            'value'                 => $value,
            'expected'              => $expected,
            'tags'                  => ['column'],
        ]);
    }
}
