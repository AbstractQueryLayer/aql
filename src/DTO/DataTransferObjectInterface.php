<?php

declare(strict_types=1);

namespace IfCastle\AQL\DTO;

use IfCastle\AQL\Executor\HydratorInterface;
use IfCastle\TypeDefinitions\DefinitionStaticAwareInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableInterface;
use IfCastle\TypeDefinitions\Value\InstantiateInterface;

interface DataTransferObjectInterface extends ArraySerializableInterface,
    InstantiateInterface,
    DefinitionStaticAwareInterface,
    HydratorInterface
{
    public static function getDtoMap(): DtoMap;
}
