<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

/**
 * Interface HydratorI
 * Describe a class that can hydrate data from a DataBase into an object
 * and extract data from an object into a key-value array for DataBase.
 *
 * @package IfCastle\AQL\Executor
 */
interface HydratorInterface
{
    /**
     * Hydrate data from a DataBase into an object.
     *
     * @param   array $data         Data to hydrate.
     * @return  static              Hydrated object.
     */
    public static function hydrate(array $data): static;

    /**
     * Extract data from an object into a key-value array for DataBase.
     *
     * @return array Extracted data array.
     */
    public function extract(): array;
}
