<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where as WhereNode;

class Where extends Conditions
{
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): WhereNode
    {
        // Check current token should be WHERE keyword
        if (\strtoupper($tokens->currentTokenAsString()) === 'WHERE') {
            $conditions             = $this->parseConditions($tokens->nextTokens());
        } else {
            $conditions             = $this->newConditionsNode(ConditionsInterface::TYPE_AND);
        }

        return (new WhereNode())->apply($this->conjuncts($conditions));
    }
}
