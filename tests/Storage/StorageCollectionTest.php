<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

class StorageCollectionTest extends BaseTestCase
{
    public function testFindStorage(): void
    {
        $collection                 = new StorageCollection();
        $collection->resolveDependencies($this->getDiContainer());

        $storage                    = $this->createMock(StorageInterface::class);

        $collection->addStorage('test_storage', $storage);

        $foundStorage               = $collection->findStorage('test_storage');
        $this->assertSame($storage, $foundStorage);
    }

    public function testRegisterStorage(): void
    {
        $collection                 = new StorageCollection();
        $collection->resolveDependencies($this->getDiContainer());

        $collection->registerStorage('test_storage', SomeStorageMock::class);

        $foundStorage               = $collection->findStorage('test_storage');

        $this->assertInstanceOf(SomeStorageMock::class, $foundStorage);
    }
}
