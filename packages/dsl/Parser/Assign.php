<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\Assign as AssignNode;
use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;

class Assign extends AqlParserAbstract
{
    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): NodeInterface
    {
        // 1. First parse left side
        $left                       = $this->parseOperand($tokens);

        if ($left instanceof ColumnInterface === false) {
            throw new ParseException('Left side of assign should be a Column');
        }

        // 2. Operation =
        if ($tokens->currentTokenAsString() !== '=') {
            throw new ParseException('Expected "=" in assign expression');
        }

        $tokens->nextToken();

        // 3. Right side
        $right                      = $this->parseOperand($tokens, true);

        if ($right instanceof ConstantInterface === false && $right instanceof SubqueryInterface === false) {
            throw new ParseException('Left side of assign should be a Constant or Subquery');
        }

        return new AssignNode($left, $right);
    }
}
