<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\FunctionReference;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface FunctionReferenceInterface extends NodeInterface
{
    public function getFunctionName(): string;

    public function getFunctionParameters(): array;

    public function isFunctionPure(): bool;

    public function isVirtual(): bool;

    public function isGlobal(): bool;

    public function isNotVirtual(): bool;

    public function getEntityName(): ?string;

    public function asVirtual(): static;

    public function asGlobal(): static;

    public function setEntityName(string $entityName): static;

    public function isEqual(FunctionReferenceInterface $functionReference): bool;

    public function isResolved(): bool;

    public function cloneAsResolved(): static;

    public function resolveSelf(): void;
}
