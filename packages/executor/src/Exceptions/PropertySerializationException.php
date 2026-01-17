<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Exceptions;

use IfCastle\AQL\Entity\Property\PropertyInterface;

class PropertySerializationException extends ColumnValueException
{
    public function __construct(
        PropertyInterface        $column,
        bool                     $isSerialize,
        string                   $value = '***',
        string                   $expected = ''
    ) {
        parent::__construct($column, $isSerialize ? 'serialize error' : 'unserialize error', $value, $expected);
    }
}
