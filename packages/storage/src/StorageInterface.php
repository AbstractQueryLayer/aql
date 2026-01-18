<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

use IfCastle\AQL\Storage\Exceptions\StorageException;

/**
 * Storage - abstractions over the concept of Database.
 * Can be either a file system or any other type of storage.
 *
 */
interface StorageInterface
{
    public const string MAX_ATTEMPTS    = 'max_attempts';

    public const string OPTIONS         = 'options';


    /**
     * Returns name of storage (null means default).
     */
    public function getStorageName(): ?string;

    /**
     * Setup storage name.
     * @return $this
     */
    public function setStorageName(string $storageName): static;

    public function connect(): void;

    public function getLastError(): ?StorageException;

    public function disconnect(): void;
}
