<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Populator;

interface DatasetInterface
{
    public function isRemoveExisted(): bool;

    public function asRemoveExisted(): static;

    public function addPopulator(PopulatorInterface $populator): static;

    public function instantiatePopulator(string $name): PopulatorInterface;

    public function getPopulators(): array;

    public function getPopulatorByName(string $name): PopulatorInterface;

    public function findPopulatorByName(string $name): ?PopulatorInterface;

    public function populateAll(): void;
}
