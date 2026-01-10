<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Entity\Builder\EntityAspectBuilderFactory;
use IfCastle\AQL\Entity\Builder\EntityAspectBuilderFactoryInterface;
use IfCastle\AQL\Entity\Builder\EntityBuilder;
use IfCastle\AQL\Entity\Builder\EntityBuilderInterface;
use IfCastle\AQL\Entity\Builder\NamingStrategy\NamingStrategyInterface;
use IfCastle\AQL\Entity\Builder\NamingStrategy\SnakeTableCamelFieldNaming;
use IfCastle\AQL\Entity\Manager\EntityDescriptorFactoryInterface;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Entity\Manager\EntityMemoryFactory;
use IfCastle\AQL\Entity\Manager\EntityStorageInterface;
use IfCastle\AQL\Executor\Entities\Book;
use IfCastle\AQL\Storage\SomeStorageMock;
use IfCastle\AQL\Storage\SqlStorageMock;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\Storage\StorageInterface;
use IfCastle\DI\ComponentRegistryInMemory;
use IfCastle\DI\ComponentRegistryInterface;
use IfCastle\DI\ConfigMutable;
use IfCastle\DI\ContainerBuilder;
use IfCastle\DI\ContainerInterface;
use IfCastle\DI\Resolver;
use PHPUnit\Framework\TestCase;

abstract class SqlQueryExecutorTestCase extends TestCase
{
    protected ?ContainerInterface $container    = null;

    protected function getDiContainer(): ContainerInterface
    {
        if ($this->container !== null) {
            return $this->container;
        }

        $builder                    = new ContainerBuilder(resolveScalarAsConfig: false);

        $builder->bindConstructible(EntityBuilderInterface::class, EntityBuilder::class)
            ->bindConstructible(EntityAspectBuilderFactoryInterface::class, EntityAspectBuilderFactory::class)
            ->bindConstructible(
                [EntityFactoryInterface::class, EntityDescriptorFactoryInterface::class, EntityStorageInterface::class],
                EntityMemoryFactory::class
            )
            ->bindConstructible(NamingStrategyInterface::class, SnakeTableCamelFieldNaming::class)
            ->bindInjectable(AqlExecutorInterface::class, AqlExecutorDummy::class)
            ->bindConstructible(StorageInterface::class, SqlStorageMock::class);

        $builder->bindInitializer(StorageCollectionInterface::class, static::defineStorageCollection(...));

        $builder->set('entityNamespaces', [Book::getBaseDir() => Book::namespace()]);

        // Create a new registry and add the component configuration
        $registry                   = new ComponentRegistryInMemory();
        $registry->addComponentConfig(EntityAspectBuilderFactoryInterface::class, new ConfigMutable([
            'namespaces'            => ['IfCastle\\AQL\\Aspects'],
        ]));

        $builder->bindObject(ComponentRegistryInterface::class, $registry);

        $builder->bindSelfReference();

        $this->container             = $builder->buildContainer(new Resolver());

        return $this->container;
    }

    protected static function defineStorageCollection(ContainerInterface $container): StorageCollectionInterface
    {
        $storageCollection          = new StorageCollection([
            StorageCollectionInterface::STORAGE_MAIN    => SqlStorageMock::class,
            SomeStorageMock::NAME                       => SomeStorageMock::class,
        ]);

        $storageCollection->resolveDependencies($container);

        return $storageCollection;
    }

    public function executeCase(SqlQueryCaseDescriptor $descriptor): void
    {
        $aqlQuery                   = (new AqlParser())->parse($descriptor->aql);
        $queryExecutor              = $this->newSqlQueryExecutor();
        $sqlStorageMock             = $this->getSqlStorageMock();

        $this->assertInstanceOf(QueryInterface::class, $aqlQuery, 'Query should be instance of ' . QueryInterface::class);

        $sqlStorageMock->reset();

        $queryExecutor->executeQuery($aqlQuery);

        $this->assertSqlStrings($descriptor->sql, $sqlStorageMock->getLastSql() ?? 'no query', 'Sql should be equals');
    }

    public function executePlan(PlanCaseDescriptor $descriptor): void
    {
        $aqlQuery                   = (new AqlParser())->parse($descriptor->aql);
        $queryExecutor              = $this->newSqlQueryExecutor();
        $storageCollection          = $this->getStorageCollection();

        $this->assertInstanceOf(QueryInterface::class, $aqlQuery, 'Query should be instance of ' . QueryInterface::class);

        foreach ($descriptor->storedResult as [$result, $storageName]) {
            $storage                = $storageCollection->findStorage($storageName) ?? throw new \Exception("Storage '$storageName' not found");

            if (\method_exists($storage, 'reset')) {
                $storage->reset();
            }
        }

        foreach ($descriptor->storedResult as $aql => [$result, $storageName]) {
            $storage                = $storageCollection->findStorage($storageName);

            if (false === \property_exists($storage, 'queryResults')) {
                throw new \Exception('Storage should have property queryResults');
            }

            $storage->queryResults[$aql] = $result;
        }

        $actualResult               = $queryExecutor->executeQuery($aqlQuery);

        foreach ($descriptor->storedResult as $sql => [, $storageName]) {

            $storage                = $storageCollection->findStorage($storageName);

            $this->assertNotEmpty($storage->executedSql, 'Executed sql should not be empty, awaiting for ' . $sql);

            $executedSql           = \array_shift($storage->executedSql);

            $this->assertSqlStrings($sql, $executedSql, 'Sql should be equals');
        }

        if (\is_array($descriptor->expectedResult)) {
            $this->assertEquals($descriptor->expectedResult, $actualResult->finalize()->toArray(), 'Result should be equals');
        }
    }

    protected function getStorageCollection(): StorageCollectionInterface
    {
        return $this->getDiContainer()->resolveDependency(StorageCollectionInterface::class);
    }

    /**
     * @throws \Exception
     */
    protected function getSqlStorageMock(): SqlStorageMock
    {
        $storage                    = $this->getStorageCollection()->findStorage(StorageCollectionInterface::STORAGE_MAIN);

        if ($storage instanceof SqlStorageMock) {
            return $storage;
        }

        throw new \Exception('StorageSqlMock failed');
    }

    protected function newSqlQueryExecutor(): QueryExecutorInterface
    {
        $executor                   = new SqlQueryExecutor();
        $executor->resolveDependencies($this->getDiContainer());

        return $executor;
    }

    public function assertSqlStrings(string $expected, string $actual, string $comment = ''): void
    {
        $actual                     = \trim((string) \preg_replace(['/\s+/'], ' ', $actual));
        $expected                   = \trim((string) \preg_replace(['/\s+/'], ' ', $expected));

        $this->assertEquals($expected, $actual, $comment);
    }
}
