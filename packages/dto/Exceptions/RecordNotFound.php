<?php

declare(strict_types=1);

namespace IfCastle\AQL\DTO\Exceptions;

use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\Exceptions\RuntimeException;

class RecordNotFound extends RuntimeException
{
    protected string $template      = '{entity} with {key}: {value} is not found';

    public function __construct(EntityInterface|string $entity, mixed $value = null)
    {
        parent::__construct([
            'entity'                => $entity instanceof EntityInterface ? $entity->getEntityName() : $entity,
            'key'                   => $entity instanceof EntityInterface ? $entity->getPrimaryKey()?->getKeyName() ?? '*' : '*',
            'value'                 => \is_scalar($value) ? $value : \get_debug_type($value),
        ]);
    }
}
