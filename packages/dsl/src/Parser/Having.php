<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Sql\Query\Expression\Having as HavingNode;

class Having extends Conditions
{
    /** Parses `HAVING conditions` after GROUP BY; an empty HAVING node when the clause is absent. */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): HavingNode
    {
        if (\strtoupper($tokens->currentTokenAsString()) !== 'HAVING') {
            return new HavingNode();
        }

        return (new HavingNode())->apply($this->conjuncts($this->parseConditions($tokens->nextTokens())));
    }
}
