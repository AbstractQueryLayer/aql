<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

class StorageCollectionByConfigTest extends BaseTestCase
{
    public function testFindStorage(): void
    {
        $this->getConfigMutable()->setSection('storages', [
            'test_storage'          => [
                'class'             => SomeStorageMock::class,
            ],
        ]);

        $collection                 = new StorageCollectionByConfig();
        $collection->resolveDependencies($this->getDiContainer());

        $foundStorage               = $collection->findStorage('test_storage');
        $this->assertInstanceOf(SomeStorageMock::class, $foundStorage);
    }

    public function testStoragesAreBuiltWithTheCollection(): void
    {
        $this->getConfigMutable()->setSection('storages', [
            'test_storage'          => [
                'class'             => SomeStorageMock::class,
            ],
        ]);

        $collection                 = new StorageCollectionByConfig();
        $collection->resolveDependencies($this->getDiContainer());

        // Before any findStorage(): the storage object already exists.
        $built                      = (fn() => \array_filter($this->storageList, \is_object(...)))->call($collection);

        $this->assertSame(['test_storage'], \array_keys($built));
        $this->assertSame($built['test_storage'], $collection->findStorage('test_storage'));
    }
}
