<?php

declare(strict_types=1);

namespace IfCastle\AQL\Generator\Ddl;

use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\Storage\StorageInterface;

final readonly class EntityToTableFactoryByStorages implements EntityToTableFactoryInterface
{
    public function __construct(
        private StorageInterface|null $storage = null,
        private StorageCollectionInterface|null $storageCollection = null,
    ) {}

    /**
     * @throws StorageException
     */
    #[\Override]
    public function newEntityToTableGenerator(EntityInterface $entity): EntityToTableInterface
    {
        $storage                    = $this->findStorage($entity->getStorageName());

        if ($storage === null) {
            throw new StorageException([
                'template'          => 'Storage {storage} for entity {entity} is not found',
                'entity'            => $entity->getEntityName(),
                'storage'           => $entity->getStorageName(),
            ]);
        }

        if ($storage instanceof EntityToTableFactoryInterface) {
            return $storage->newEntityToTableGenerator($entity);
        }

        throw new StorageException([
            'template'              => 'Storage {storage} for entity {entity} does not support entity to table generation',
            'storage'               => $entity->getStorageName(),
            'entity'                => $entity->getEntityName(),
        ]);
    }

    private function findStorage(string $storageName): ?StorageInterface
    {
        if ($this->storageCollection === null) {
            return $this->storage;
        }

        return $this->storageCollection->findStorage($storageName);
    }
}
