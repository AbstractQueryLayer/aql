<?php

declare(strict_types=1);

namespace IfCastle\Jobs;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Parser\AqlParser;
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
use IfCastle\AQL\Executor\AdditionalOptions;
use IfCastle\AQL\Executor\AqlExecutor;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Result\TupleInterface;
use IfCastle\AQL\Storage\Exceptions\DuplicateKeysException;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Storage\StorageCollection;
use IfCastle\AQL\Storage\StorageCollectionInterface;
use IfCastle\AQL\Storage\StorageInterface;
use IfCastle\AQL\Transaction\IsolationLevelEnum;
use IfCastle\AQL\Transaction\Transaction;
use IfCastle\AQL\Transaction\TransactionStatusEnum;
use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ComponentRegistryInMemory;
use IfCastle\DI\ComponentRegistryInterface;
use IfCastle\DI\ConfigMutable;
use IfCastle\DI\ConstructibleDependencyByReflection;
use IfCastle\DI\ContainerBuilder;
use IfCastle\DI\Resolver;
use IfCastle\Exceptions\BaseException;

/**
 * JobDatabaseInterface on an AQL executor and the main storage of a storage collection.
 *
 * Storage errors leave as \RuntimeException naming no SQL, and AQL parse or plan errors as \LogicException: driver
 * and parser messages quote the statement, whose parameters are job payloads.
 */
final class AqlJobDatabase implements JobDatabaseInterface
{
    public function __construct(
        private readonly StorageCollectionInterface $collection,
        private readonly AqlExecutorInterface $executor,
        private readonly ?Transaction $transaction = null,
    ) {}

    /**
     * A job database of its own on $storage, outside an application container: a worker process, a test. The entity
     * model comes from $entityNamespaces with snake_case tables and camelCase columns; the caller owns the storage
     * and disconnects it.
     *
     * @param list<string> $entityNamespaces where the Job and JobAttempt entities are found; name exactly one
     *     source of both, since the first namespace holding a short name wins
     */
    public static function standalone(StorageInterface $storage,
        array $entityNamespaces = ['IfCastle\\Jobs\\Entity']): self
    {
        $storage->setStorageName(StorageCollectionInterface::STORAGE_MAIN);
        $collection = new StorageCollection([StorageCollectionInterface::STORAGE_MAIN => $storage]);
        $registry = new ComponentRegistryInMemory();
        $registry->addComponentConfig(EntityAspectBuilderFactoryInterface::class, new ConfigMutable([
            'namespaces' => ['IfCastle\\AQL\\Aspects'],
        ]));
        $builder = new ContainerBuilder(resolveScalarAsConfig: false);
        $builder->bindSelfReference()
            ->bindObject(StorageCollectionInterface::class, $collection)
            ->bindConstructible(EntityBuilderInterface::class, EntityBuilder::class)
            ->bindConstructible(EntityAspectBuilderFactoryInterface::class, EntityAspectBuilderFactory::class)
            ->bind(
                [EntityFactoryInterface::class, EntityDescriptorFactoryInterface::class, EntityStorageInterface::class],
                new ConstructibleDependencyByReflection(EntityMemoryFactory::class, resolveScalarAsConfig: false)
            )
            ->bindConstructible(NamingStrategyInterface::class, SnakeTableCamelFieldNaming::class)
            ->bindConstructible(AqlExecutorInterface::class, AqlExecutor::class)
            ->bindObject(ComponentRegistryInterface::class, $registry)
            ->set('entityNamespaces', $entityNamespaces);
        $container = $builder->buildContainer(new Resolver());
        $collection->resolveDependencies($container);
        if ($storage instanceof AutoResolverInterface) {
            $storage->resolveDependencies($container);
        }

        return new self($collection, $container->resolveDependency(AqlExecutorInterface::class));
    }

    #[\Override]
    public function query(string $aql, array $parameters = []): ResultInterface
    {
        try {
            $query = (new AqlParser())->parse($aql);
            if (!$query instanceof BasicQueryInterface) {
                throw new \LogicException('Job query could not be built');
            }

            $query->applyParameters($parameters);

            return $this->executor->executeAql($query, $this->transaction === null ? null
                : new AdditionalOptions([], $this->transaction))->finalize();
        } catch (DuplicateKeysException $error) {
            throw $error;
        } catch (StorageException) {
            throw new \RuntimeException('Job storage operation failed');
        } catch (BaseException) {
            throw new \LogicException('Job query could not be built');
        }
    }

    #[\Override]
    public function select(string $aql, array $parameters = []): array
    {
        $result = $this->query($aql, $parameters);

        return $result instanceof TupleInterface ? $result->toArray()
            : throw new \LogicException('Expected rows');
    }

    #[\Override]
    public function transaction(callable $operation, ?IsolationLevelEnum $isolation = null): mixed
    {
        if ($this->transaction !== null) {
            throw new \LogicException('Job transactions do not nest');
        }

        $transaction = new Transaction(isolationLevel: $isolation);
        try {
            $result = $operation(new self($this->collection, $this->executor, $transaction));
            $transaction->commit();

            return $result;
        } catch (\Throwable $error) {
            if (\in_array($transaction->getStatus(), [TransactionStatusEnum::UNDEFINED, TransactionStatusEnum::OPENED],
                true)) {
                $transaction->rollBack($error);
            }

            throw $error;
        }
    }
}
