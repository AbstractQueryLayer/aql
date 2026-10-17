<?php

declare(strict_types=1);

namespace IfCastle\AQL\OpenSearch\Storage;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\FunctionHandlerInterface;
use IfCastle\AQL\Executor\QueryExecutorInterface;
use IfCastle\AQL\Executor\QueryExecutorResolverInterface;
use IfCastle\AQL\OpenSearch\Executor\OpenSearchQueryExecutor;
use IfCastle\AQL\Result\ResultFetched;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Storage\AqlStorageInterface;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ContainerInterface;

/**
 * An AQL storage on an OpenSearch node: entities stored here are queried with SELECT, and the storage renders each
 * transformed query to the search API (SearchRequestRenderer) and returns the hits as rows.
 *
 * Only SELECT is served: writes, index creation and settling of writes have no AQL statement that carries what the
 * engine needs (external versions, bulk items, refresh), so they stay with the application. An entity's table name
 * is the index; a query may name another index of the same mapping by setting its subject's resolved name.
 * Transport failures propagate as the transport throws them.
 */
class OpenSearch implements AqlStorageInterface, QueryExecutorResolverInterface, FunctionHandlerInterface,
    AutoResolverInterface
{
    final public const string NAME = 'opensearch';

    private ?string $storageName = self::NAME;
    private readonly SearchRequestRenderer $renderer;
    /** The container the executors of this storage resolve their dependencies from. */
    private ?ContainerInterface $container = null;

    public function __construct(private readonly OpenSearchTransportInterface $transport,
        MatchPolicy $policy = new MatchPolicy())
    {
        $this->renderer = new SearchRequestRenderer($policy);
    }

    #[\Override]
    public function getStorageName(): ?string
    {
        return $this->storageName;
    }

    #[\Override]
    public function setStorageName(string $storageName): static
    {
        $this->storageName = $storageName;

        return $this;
    }

    #[\Override]
    public function connect(): void {}

    #[\Override]
    public function getLastError(): ?StorageException
    {
        return null;
    }

    #[\Override]
    public function disconnect(): void {}

    #[\Override]
    public function resolveDependencies(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    #[\Override]
    public function resolveQueryExecutor(BasicQueryInterface $basicQuery,
        ?EntityInterface $entity = null): ?QueryExecutorInterface
    {
        $executor = new OpenSearchQueryExecutor();
        $executor->resolveDependencies($this->container
            ?? throw new \LogicException('Resolve storage dependencies before AQL queries'));

        return $executor;
    }

    /** The full-text functions are this storage's own; any other function stays unresolved and fails the query. */
    #[\Override]
    public function handleFunction(FunctionReferenceInterface $function, NodeContextInterface $context): void
    {
        if (SearchRequestRenderer::isTextFunction($function->getFunctionName())) {
            $function->resolveSelf();
        }
    }

    /**
     * @throws StorageException for anything but a SELECT the renderer can express
     */
    #[\Override]
    public function executeAql(BasicQueryInterface $query, ?object $context = null): ResultInterface
    {
        $query = $query->resolveNode();
        if (!$query instanceof QueryInterface || $query->getResolvedAction() !== QueryInterface::ACTION_SELECT) {
            throw new StorageException([
                'template' => 'The OpenSearch storage runs SELECT only, not {query}',
                'query' => $query->getAql(),
            ]);
        }

        $request = $this->renderer->render($query);
        $body = $request->body;
        $rows = [];
        do {
            $hits = $this->hits($request->index, $body);
            foreach ($hits as $hit) {
                $rows[] = self::row($hit['_source'] ?? [], $request->columns);
            }

            $body['search_after'] = \end($hits)['sort'] ?? null;
        } while ($request->everyRow && \count($hits) === $body['size']);

        return new ResultFetched($rows);
    }

    /** @return list<array<mixed>> */
    private function hits(string $index, array $body): array
    {
        $hits = $this->transport->request('POST', '/' . \rawurlencode($index) . '/_search', $body)['hits']['hits']
            ?? null;
        if (!\is_array($hits) || !\array_is_list($hits)) {
            throw new StorageException(['template' => 'OpenSearch returned no hits list for {index}',
                'index' => $index]);
        }

        return $hits;
    }

    /**
     * A hit as a row: every selected column in tuple order, null where the document has no value.
     *
     * @param array<string, string> $columns
     */
    private static function row(array $source, array $columns): array
    {
        $row = [];
        foreach ($columns as $field => $key) {
            $row[$key] = $source[$field] ?? null;
        }

        return $row;
    }
}
