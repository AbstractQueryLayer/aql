<?php

declare(strict_types=1);

namespace IfCastle\AQL\OpenSearch\Executor;

use IfCastle\AQL\Executor\QueryExecutorAbstract;

/**
 * Normalises a query for the OpenSearch storage: entities, properties, parameters and functions are resolved by the
 * common executor, and the storage renders the result; nothing here is specific to the engine.
 */
class OpenSearchQueryExecutor extends QueryExecutorAbstract {}
