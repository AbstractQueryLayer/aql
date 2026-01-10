<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\ActivationStatus;

use IfCastle\AQL\Entity\Property\PropertyBoolean;

class PropertyIsEnabled extends PropertyBoolean
{
    protected ?string $typicalName = ActivationStatus::IS_ENABLED;
}
