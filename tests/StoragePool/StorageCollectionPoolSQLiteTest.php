<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Key\Key;
use IfCastle\AQL\Entity\Populator\Dataset;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\Entity\Property\PropertyString;
use IfCastle\AQL\Executor\Context\ContextVariablesInterface;
use IfCastle\AQL\SQLite\Storage\SQLite;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\TestCases\MemorySQLiteStorage;
use IfCastle\AQL\TestCases\TestCaseWithDiContainer;
use IfCastle\AQL\Transaction\Transaction;
use IfCastle\AQL\Transaction\TransactionAwareInterface;
use IfCastle\AQL\Transaction\TransactionInterface;
use IfCastle\DesignPatterns\Factory\FactoryInterface;
use IfCastle\DI\ContainerBuilder;
use IfCastle\DI\ContainerInterface;

/** Storages found in a StorageCollectionPool are pooled SQLite drivers that the executor uses as any other. */
final class StorageCollectionPoolSQLiteTest extends TestCaseWithDiContainer
{
    private static ?StoragePool $pool = null;

    private static ?SwitchableContextVariables $context = null;

    public function testAQueryRunsThroughAStorageFoundByName(): void
    {
        $storage                    = $this->collection()->findStorage('main');

        $this->assertInstanceOf(SQLite::class, $storage);
        $this->assertSame([['one' => 1]], $storage->executeSql('SELECT 1 AS one')->toArray());

        $this->createPairs();
        $rows                       = $this->getAqlExecutor()
            ->executeAql((new AqlParser())->parse('SELECT id FROM Pair WHERE first = "z" ORDER BY id'))
            ->finalize()
            ->toArray();

        $this->assertSame([2, 3], \array_map('intval', \array_column($rows, 'id')));
    }

    public function testAContextHoldsOneStorageUntilItEnds(): void
    {
        $storage                    = $this->collection()->findStorage();

        $this->assertSame($storage, $this->collection()->findStorage('main'));
        $this->assertSame(1, self::$pool->getUsed());

        self::$context->startNewContext();

        $this->assertSame(0, self::$pool->getUsed());
        $this->assertSame($storage, $this->collection()->findStorage('main'));
    }

    public function testReleaseRollsBackAnOpenTransactionAndReportsIt(): void
    {
        $storage                    = $this->collection()->findStorage('main');
        $this->assertInstanceOf(SQLite::class, $storage);
        $storage->executeSql('CREATE TABLE note (id INTEGER PRIMARY KEY)');

        $outer                      = new Transaction();
        $inner                      = (new Transaction())->setParentTransaction($outer);
        $storage->executeSql('INSERT INTO note (id) VALUES (1)', $this->inTransaction($inner));

        try {
            $this->collection()->releaseStorages();
            $this->fail('An open transaction must be reported');
        } catch (StorageException $exception) {
            $this->assertStringContainsString('was open when the storage was released', $exception->getMessage());
        }

        $this->assertSame(0, self::$pool->getUsed());

        $again                      = $this->collection()->findStorage('main');

        $this->assertSame($storage, $again);
        $this->assertInstanceOf(SQLite::class, $again);
        $this->assertNull($again->getTransaction());
        $this->assertSame([], $again->executeSql('SELECT id FROM note')->toArray());

        $next                       = new Transaction();
        $again->executeSql('INSERT INTO note (id) VALUES (2)', $this->inTransaction($next));
        $next->commit();

        $this->assertSame([['id' => 2]], $again->executeSql('SELECT id FROM note')->toArray());
    }

    public function testAnUnknownStorageIsNull(): void
    {
        $this->assertNull($this->collection()->findStorage('unknown'));
    }

    /** A second collection keeps its own storages in the same context, so it borrows from the pool too. */
    public function testAnExhaustedPoolIsReported(): void
    {
        $this->collection()->findStorage('main');
        $this->sharingPool()->findStorage('main');

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage("has no free storage: '2' of '2' are borrowed");

        $this->sharingPool()->findStorage('main');
    }

    #[\Override]
    protected function buildDiContainer(ContainerBuilder $containerBuilder): void
    {
        parent::buildDiContainer($containerBuilder);

        self::$context              = new SwitchableContextVariables();
        $containerBuilder->bindObject(ContextVariablesInterface::class, self::$context);
    }

    #[\Override]
    protected static function defineStorageCollection(ContainerInterface $container): StorageCollectionInterface
    {
        $factory                    = new readonly class ($container) implements FactoryInterface {
            public function __construct(private ContainerInterface $container) {}

            #[\Override]
            public function createObject(): object
            {
                return StorageCollection::instanciateStorage('main', MemorySQLiteStorage::class, $this->container);
            }
        };

        self::$pool                 = new StoragePool($factory, 2);
        $collection                 = new StorageCollectionPool();
        $collection->resolveDependencies($container);
        $collection->addStoragePool('main', self::$pool);

        return $collection;
    }

    #[\Override]
    protected function tearDown(): void
    {
        self::$context?->startNewContext();
        parent::tearDown();
        self::$pool                 = null;
        self::$context              = null;
    }

    private function collection(): StorageCollectionPool
    {
        return $this->getDiContainer()->resolveDependency(StorageCollectionInterface::class);
    }

    /** Another collection over the same pool, resolved in the same context. */
    private function sharingPool(): StorageCollectionPool
    {
        $collection                 = new StorageCollectionPool();
        $collection->resolveDependencies($this->getDiContainer());
        $collection->addStoragePool('main', self::$pool);

        return $collection;
    }

    private function inTransaction(TransactionInterface $transaction): TransactionAwareInterface
    {
        return new readonly class ($transaction) implements TransactionAwareInterface {
            public function __construct(private TransactionInterface $transaction) {}

            #[\Override]
            public function getTransaction(): TransactionInterface
            {
                return $this->transaction;
            }
        };
    }

    private function createPairs(): void
    {
        $this->getEntityFactory()->setEntity(new class extends EntityAbstract {
            protected function beforeBuild(): void
            {
                $this->name         = 'Pair';
            }

            protected function buildAspects(): void {}

            protected function buildProperties(): void
            {
                $this->describeProperty((new PropertyInteger('id'))->asPrimaryKey()->asAutoIncrement())
                    ->describeProperty(new PropertyString('first'));

                $this->describeKey((new Key('id'))->asPrimary());
            }
        });

        $dataset                    = Dataset::instantiate($this->getDiContainer());
        $dataset->asRemoveExisted();
        $dataset->instantiatePopulator('pair')
            ->setTemplateData([['first' => 'x'], ['first' => 'z'], ['first' => 'z']])
            ->populate();
    }
}
