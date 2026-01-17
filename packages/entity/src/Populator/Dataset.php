<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Populator;

use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\Exceptions\LogicalException;

class Dataset implements DatasetInterface, AutoResolverInterface
{
    public static function instantiate(ContainerInterface $container): static
    {
        $self                       = new static();
        $self->resolveDependencies($container);

        return $self;
    }

    protected ContainerInterface $diContainer;

    protected EntityFactoryInterface $entityFactory;

    protected array $populators     = [];

    protected bool $isRemoveExisted = false;

    #[\Override]
    public function resolveDependencies(ContainerInterface $container): void
    {
        $this->diContainer          = $container;
        $this->entityFactory        = $container->resolveDependency(EntityFactoryInterface::class);
    }

    #[\Override]
    public function isRemoveExisted(): bool
    {
        return $this->isRemoveExisted;
    }

    #[\Override]
    public function asRemoveExisted(): static
    {
        $this->isRemoveExisted      = true;

        return $this;
    }

    #[\Override]
    public function addPopulator(PopulatorInterface $populator): static
    {
        $this->populators[$populator->getPopulatorName()] = $populator;

        return $this;
    }

    #[\Override]
    public function instantiatePopulator(string $name): PopulatorInterface
    {
        $entity                     = $this->entityFactory->getEntity($name);
        $populator                  = $entity instanceof PopulatorAwareInterface ? $entity->getPopulator() : new Populator($entity);

        if ($populator instanceof AutoResolverInterface) {
            $populator->resolveDependencies($this->diContainer);
        }

        if ($this->isRemoveExisted) {
            $populator->asRemoveExisted();
        }

        $this->addPopulator($populator);

        return $populator;
    }

    #[\Override]
    public function getPopulators(): array
    {
        return $this->populators;
    }

    /**
     * @throws LogicalException
     */
    #[\Override]
    public function getPopulatorByName(string $name): PopulatorInterface
    {
        return $this->populators[$name] ?? throw new LogicalException([
            'template'              => 'Populator {name} is not found',
            'name'                  => $name,
        ]);
    }

    #[\Override]
    public function findPopulatorByName(string $name): ?PopulatorInterface
    {
        return $this->populators[$name] ?? null;
    }

    #[\Override]
    public function populateAll(): void
    {
        foreach ($this->populators as $populator) {
            $populator->populate();
        }
    }
}
