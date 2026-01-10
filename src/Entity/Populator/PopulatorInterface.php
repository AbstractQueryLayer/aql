<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Populator;

use IfCastle\AQL\Entity\EntityInterface;

interface PopulatorInterface
{
    public function getPopulatorName(): string;

    public function getPopulatorEntity(): EntityInterface;

    public function isRemoveExisted(): bool;

    public function asRemoveExisted(): static;

    public function populate(): void;

    public function isPopulated(): bool;

    public function getPopulatedData(): array;

    public function findPopulatedRowById(string|int|float|null $id = null): ?array;

    public function findPopulatedRowBy(string $column, mixed $value): ?array;

    public function getPopulatedFirstId(): string|int|float|null;

    public function getTemplateData(): array;

    public function setTemplateData(array $rows): static;

    public function addTemplateRow(array $row): static;

    public function getReferenceKey(string $toEntityName): ?string;
}
