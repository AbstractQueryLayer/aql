<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use IfCastle\AQL\Executor\Preprocessing\PreprocessedQuery;
use IfCastle\DI\ContainerInterface;

/**
 * A preprocessed query runs its plan once per call. Only a plan that reads other storages for a join binds its state
 * anew on each run; any other plan is refused at preprocessing.
 */
final class PreprocessedPlanTest extends SqlQueryExecutorTestCase
{
    public function testAPlanThatComposesAVirtualColumnIsRefused(): void
    {
        $query = new PreprocessedQuery(static fn() => (new AqlParser())->parse('SELECT id, authorList FROM Book'), 'authors');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/execution plan does more than read other storages/');
        $container = $this->getDiContainer();
        $executors = new class ($container) implements QueryExecutorFactoryInterface {
            public function __construct(private readonly ContainerInterface $container) {}

            #[\Override]
            public function resolveQueryExecutor(BasicQueryInterface $basicQuery, ?EntityInterface $entity = null): ?QueryExecutorInterface
            {
                $executor = new SqlQueryExecutor();
                $executor->resolveDependencies($this->container);

                return $executor;
            }
        };

        (new AqlExecutor($container->resolveDependency(EntityFactoryInterface::class), $this->getStorageCollection(), $executors))
            ->executeAql($query);
    }
}
