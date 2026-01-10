<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

interface PropertyEnumInterface extends PropertyInterface
{
    public function getVariants(): array;

    public function setVariants(array $variants): static;

    /**
     * Returns TRUE if variant is virtual,
     * this means that the numeric value is stored in the database and the variants are stored in the code.
     */
    public function isVariantsVirtual(): bool;

    public function setVariantsVirtual(bool $isVariantsVirtual): static;
}
