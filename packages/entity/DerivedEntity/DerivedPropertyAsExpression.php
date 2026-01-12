<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\DerivedEntity;

use IfCastle\AQL\Entity\Property\PropertyAbstract;

final class DerivedPropertyAsExpression extends PropertyAbstract
{
    protected bool $isVirtual       = true;

    public function __construct(
        string $name,
        string $type = self::T_STRING,
        bool   $isNullable = false
    ) {
        parent::__construct($name, $type, $isNullable);
    }


}
