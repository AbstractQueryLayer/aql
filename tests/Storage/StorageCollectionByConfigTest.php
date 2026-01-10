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
}
