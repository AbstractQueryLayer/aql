<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity;

use IfCastle\AQL\Entity\Builder\EntityBuilderInterface;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Entity\Manager\EntityStorageInterface;
use IfCastle\DI\ContainerInterface;

trait EntityDependenciesTrait
{
    abstract protected function getDiContainer(): ContainerInterface;

    protected function getEntityFactory(): EntityFactoryInterface
    {
        return $this->getDiContainer()->getDependency(EntityFactoryInterface::class);
    }

    protected function getEntityStorage(): EntityStorageInterface
    {
        return $this->getDiContainer()->getDependency(EntityStorageInterface::class);
    }

    protected function getEntity(string $entityName, bool $isRaw = false): EntityInterface
    {
        return $this->getEntityFactory()->getEntity($entityName, $isRaw);
    }

    protected function setEntity(EntityInterface $entity): static
    {
        $this->getEntityStorage()->setEntity($entity);

        return $this;
    }

    protected function getEntityBuilder(): EntityBuilderInterface
    {
        return $this->getDiContainer()->resolveDependency(EntityBuilderInterface::class);
    }
}
