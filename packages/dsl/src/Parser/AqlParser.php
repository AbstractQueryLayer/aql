<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\Exceptions\UnexpectedValue;

class AqlParser extends AqlParserAbstract
{
    public static function withAllOptions(TokensIteratorInterface $tokens): void
    {
        // Allow all possible options for the query
        $tokens->withOptions(With::ALLOW_CTE,
            Select::ALLOW_UNION,
            Join::ALLOW_JOINS, Join::ALLOW_JOIN_CONDITIONS,
            Join::ALLOW_JOIN_DEPENDENT, Join::ALLOW_DERIVED_TABLE,
            Join::ALLOW_FROM_SELECT,
        );
    }

    public static function createTokenIterator(string $code): TokensIteratorInterface
    {
        $tokens                     = new TokensIterator($code);
        static::withAllOptions($tokens);
        return $tokens;
    }

    /**
     * @throws UnexpectedValue
     * @throws ParseException
     */
    #[\Override]
    public function parse(string $code): NodeInterface
    {
        $tokens                     = static::createTokenIterator($code);
        $node                       = $this->parseTokens($tokens);

        $tokens->throwIfNotEnded();

        return $node;
    }

    /**
     * @throws ParseException
     * @throws UnexpectedValue
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): QueryInterface
    {
        return match ($tokens->currentTokenAsString()) {
            QueryInterface::ACTION_WITH                                   => new With()->parseTokens($tokens),
            QueryInterface::ACTION_SELECT, QueryInterface::ACTION_COUNT   => new Select()->parseTokens($tokens),
            QueryInterface::ACTION_INSERT                                 => new Insert()->parseTokens($tokens),
            QueryInterface::ACTION_UPDATE, QueryInterface::ACTION_REPLACE => new Update()->parseTokens($tokens),
            QueryInterface::ACTION_DELETE                                 => new Delete()->parseTokens($tokens),

            default                                                       => throw new ParseException(
                'Unknown query Action', ['action' => $tokens->currentTokenAsString()],
            ),
        };
    }
}
