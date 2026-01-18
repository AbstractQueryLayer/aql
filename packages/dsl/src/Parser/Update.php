<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Dsl\Sql\Query\Update as UpdateNode;

class Update extends AqlParserAbstract
{
    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): UpdateNode
    {
        if ($tokens->currentTokenAsString() !== QueryInterface::ACTION_UPDATE) {
            throw new ParseException('Expected keyword ' . QueryInterface::ACTION_UPDATE, ['line' => $tokens->getCurrentLine()]);
        }

        $tokens->increaseRecursionDepth();

        $oldStopTokens              = $tokens->getStopTokens();
        $tokens->addStopTokens([
            QueryInterface::NODE_ASSIGMENT_LIST, QueryInterface::NODE_WHERE,
            QueryInterface::NODE_ORDER_BY, QueryInterface::NODE_GROUP_BY,
            QueryInterface::NODE_LIMIT,
        ]);

        // 2. From and Joins expression
        $from                       = new From()->parseTableReference($tokens->nextTokens());
        $from->onlySubject()->withoutType();

        // 3. SET expression
        $assigns                    = new AssignmentList()->parseTokens($tokens);
        // 4. WHERE
        $where                      = new Where()->parseTokens($tokens);
        // 5. Order by
        $orderBy                    = new OrderBy()->parseTokens($tokens);
        // 6. Limit
        $limit                      = new Limit()->parseTokens($tokens);

        $tokens->setStopTokens($oldStopTokens);

        $query                      = new UpdateNode($from, $where, $limit);

        $query->setAssigmentList($assigns);

        $query->setOrderBy($orderBy);

        $tokens->decreaseRecursionDepth();

        return $query;
    }
}
