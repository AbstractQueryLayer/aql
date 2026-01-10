<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\OrderBy as OrderByNode;
use IfCastle\AQL\Dsl\Sql\Query\Expression\OrderItem as OrderItemNode;
use IfCastle\AQL\Dsl\Sql\Query\Expression\OrderItemInterface;

class OrderBy extends AqlParserAbstract
{
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): OrderByNode
    {
        // Expression: ORDER BY expression1, expression2 ASC, expression3 DESC
        if ($tokens->currentTokenAsString() !== 'ORDER') {
            return new OrderByNode();
        }

        $tokens->nextToken();

        if ($tokens->currentTokenAsString() !== 'BY') {
            throw new ParseException('Expected ORDER BY expression');
        }

        $tokens->nextToken();

        $stopTokens                 = $tokens->getStopTokens();
        $expressions                = [];

        while (true) {
            if (\array_key_exists($tokens->currentTokenAsString(), $stopTokens)) {
                break;
            }

            $expressions[]          = $this->parseItem($tokens);

            if ($tokens->currentTokenAsString() !== ',') {
                break;
            } elseif ($tokens->valid()) {
                $tokens->nextToken();
            }
        }

        if (empty($expressions)) {
            throw new ParseException('Empty ORDER BY expression is not allowed');
        }

        return new OrderByNode(...$expressions);
    }

    public function parseItem(TokensIteratorInterface $tokens): NodeInterface
    {
        // 1. First parse expression as left
        $expression                 = $this->parseOperand($tokens);

        // 2. wait keyword DESC or ASC
        switch ($tokens->currentTokenAsString()) {
            case OrderItemInterface::DESC:
                $direction          = OrderItemInterface::DESC;
                $tokens->nextToken();
                break;
            case OrderItemInterface::ASC:
                $direction          = OrderItemInterface::ASC;
                $tokens->nextTokens();
                break;
            default:
                $direction          = OrderItemInterface::ASC;
        }

        return new OrderItemNode($expression, $direction);
    }
}
