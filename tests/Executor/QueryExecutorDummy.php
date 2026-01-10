<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use IfCastle\AQL\Executor\Plan\ExecutionContextInterface;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Result\TupleInterface;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\AQL\Storage\SomeStorageMock;

class QueryExecutorDummy extends QueryExecutorBasicAbstract
{
    /**
     * @throws QueryException
     * @throws StorageException
     */
    #[\Override]
    protected function executeQueryWithContext(BasicQueryInterface $query, ?ExecutionContextInterface $context = null): ResultInterface
    {
        $storage                    = $this->storageCollection->findStorage($query->getQueryStorage());

        if ($storage === null) {
            throw new QueryException([
                'query'             => $query,
                'template'          => 'The query storage {storage} is not found',
                'storage'           => $query->getQueryStorage(),
            ]);
        }

        if ($storage instanceof SomeStorageMock === false) {
            throw new QueryException([
                'query'             => $query,
                'template'          => 'The storage has wrong type: SomeStorageMock expected, {storage} given',
                'storage'           => $query->getQueryStorage(),
            ]);
        }

        $result                     = $storage->executeAql($query, $this->queryContext);

        // Save hidden columns count
        if ($result instanceof TupleInterface && $query instanceof QueryInterface) {
            $result->setHiddenColumns(\count($query->getTuple()?->getHiddenColumns() ?? []));
        }

        $context?->setResult($result);

        return $result;
    }
}
