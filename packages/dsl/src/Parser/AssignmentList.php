<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\AssignmentList as AssignmentListNode;
use IfCastle\AQL\Dsl\Sql\Query\Expression\AssignmentListInterface;

class AssignmentList extends AqlParserAbstract
{
    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): AssignmentListInterface
    {
        if ($tokens->currentTokenAsString() !== 'SET') {
            throw new ParseException('Expected keyword SET', ['line' => $tokens->getCurrentLine()]);
        }

        return $this->parseAssignments($tokens->nextTokens());
    }

    /**
     * @throws ParseException
     */
    public function parseAssignments(TokensIteratorInterface $tokens): AssignmentListInterface
    {
        $stopTokens                 = $tokens->getStopTokens();
        $expressions                = [];

        while (true) {
            if (\array_key_exists($tokens->currentTokenAsString(), $stopTokens)) {
                break;
            }

            $expressions[]          = (new Assign())->parseTokens($tokens);

            if ($tokens->currentTokenAsString() !== ',') {
                break;
            } elseif ($tokens->valid()) {
                $tokens->nextToken();
            }
        }

        if ($expressions === []) {
            throw new ParseException('Empty SET expression is not allowed');
        }

        return $this->createNode(...$expressions);
    }

    protected function createNode(NodeInterface ...$expressions): AssignmentListNode
    {
        return new AssignmentListNode(...$expressions);
    }
}
