<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where as WhereNode;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\NestedTuple as NestedTupleNode;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleInterface;

class NestedTuple extends Select
{
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): QueryInterface
    {
        if ($tokens->currentTokenAsString() !== '[') {
            throw new ParseException(
                \sprintf('NestedTuple: Opening parenthesis required \']\' (got %s)',
                    $tokens->currentTokenAsString())
            );
        }

        $select                     = $this->parseSelect($tokens->nextTokens());

        if ($tokens->currentTokenAsString() !== ']') {
            throw new ParseException(
                \sprintf('NestedTuple: Closing parenthesis required \']\' (got %s)',
                    $tokens->currentTokenAsString())
            );
        }

        $tokens->nextTokens();

        return $select;
    }

    #[\Override]
    protected function newSelectNode(string|JoinInterface                     $from,
        array|TupleInterface|null                $tuple = null,
        ConditionsInterface|array|WhereNode|null $where = null,
        ?LimitInterface                           $limit = null
    ): QueryInterface {
        return new NestedTupleNode($from, $tuple, $where, $limit);
    }
}
