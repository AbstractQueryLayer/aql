<?php

declare(strict_types=1);

namespace IfCastle\AQL\FileSystem\Executor;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use IfCastle\AQL\Executor\Plan\ExecutionContextInterface;
use IfCastle\AQL\Executor\QueryExecutorBasicAbstract;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Result\TupleInterface;
use IfCastle\Exceptions\UnexpectedMethodMode;

class QueryExecutor extends QueryExecutorBasicAbstract
{
    #[\Override]
    protected function normalizeQueryOrGeneratePlan(
        BasicQueryInterface $basicQuery,
        ?ExecutionContextInterface $context          = null
    ): void {
        foreach ($basicQuery->getChildNodes() as $childNode) {

            if ($childNode === null) {
                continue;
            }

            match ($childNode->getNodeName()) {
                QueryInterface::NODE_TUPLE          => $this->handleTuple($childNode, $context),
                QueryInterface::NODE_ASSIGMENT_LIST => $this->handleAssignmentList($childNode, $context),
                QueryInterface::NODE_FROM           => $this->handleFrom($childNode, $context),
                QueryInterface::NODE_WHERE          => $this->handleWhere($childNode, $context),
                QueryInterface::NODE_ORDER_BY       => $this->handleOrderBy($childNode, $context),
                QueryInterface::NODE_LIMIT          => $this->handleLimit($childNode, $context),
                default =>
                    throw new QueryException([
                        'template'      => 'Unknown node name {nodeName}',
                        'nodeName'      => $childNode->getNodeName(),
                        'query'         => $basicQuery,
                    ])
            };
        }
    }

    protected function handleTuple(TupleInterface $node, ExecutionContextInterface $context): void {}

    protected function handleAssignmentList(NodeInterface $node, ExecutionContextInterface $context): void {}

    protected function handleFrom(NodeInterface $node, ExecutionContextInterface $context): void {}

    protected function handleWhere(NodeInterface $node, ExecutionContextInterface $context): void {}

    protected function handleOrderBy(NodeInterface $node, ExecutionContextInterface $context): void {}

    protected function handleLimit(NodeInterface $node, ExecutionContextInterface $context): void {}

    #[\Override]
    protected function executeQueryWithContext(
        BasicQueryInterface       $query,
        ?ExecutionContextInterface $context = null
    ): ResultInterface {
        throw new UnexpectedMethodMode(__METHOD__, 'This method should not be used for FileSystem queries');
    }
}
