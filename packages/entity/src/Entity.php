<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity;

use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\DI\Dependency;
use IfCastle\DI\DependencyInterface;
use IfCastle\DI\DescriptorInterface;
use IfCastle\DI\FactoryInterface;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Entity extends Dependency implements FactoryInterface
{
    public function __construct(
        ?string           $entityName       = null,
        ?string           $typicalName      = null,
        array|string|null $type             = null,
        bool              $isRequired       = true,
        bool              $isLazyLoad       = false
    ) {
        if ($entityName === null || $typicalName !== null) {
            $entityName             = '@' . $typicalName;
        }

        parent::__construct($entityName, $type, $isRequired, $isLazyLoad);
    }

    #[\Override]
    public function getFactory(): FactoryInterface|null
    {
        return $this;
    }

    #[\Override]
    public function create(
        ContainerInterface  $container,
        DescriptorInterface $descriptor,
        ?DependencyInterface $forDependency = null
    ): object|null {
        $entityFactory              = $container->findDependency(EntityFactoryInterface::class);

        if ($entityFactory === null) {
            return null;
        }

        if ($entityFactory instanceof EntityFactoryInterface === false) {
            throw new \TypeError('EntityFactory is not an instance of ' . EntityFactoryInterface::class);
        }

        return $entityFactory->findEntity($descriptor->getDependencyKey());
    }
}
