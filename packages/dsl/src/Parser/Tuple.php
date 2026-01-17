<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\Tuple as TupleNode;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleInterface;
use IfCastle\Exceptions\RequiredValueEmpty;

class Tuple extends AqlParserAbstract
{
    /**
     *
     * @return NodeInterface
     * @throws Exceptions\ParseException
     * @throws RequiredValueEmpty
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): TupleInterface
    {
        //
        // Special case '*' leads to empty Tuple
        //
        if ($tokens->currentTokenAsString() === '*') {
            $tokens->nextTokens();
            return (new TupleNode())->markAsDefaultColumns();
        }

        $stopTokens                 = $tokens->getStopTokens();

        $results                    = [];

        while ($tokens->valid() && !\array_key_exists(\strtolower($tokens->currentTokenAsString()), $stopTokens)) {
            $results[]              = (new TupleColumn())->parseTokens($tokens);

            if ($tokens->currentTokenAsString() !== ',') {
                break;
            }

            $tokens->nextToken();
        }

        return new TupleNode(...$results);
    }
}
