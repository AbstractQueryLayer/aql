<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;

class Constant extends AqlParserAbstract
{
    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): NodeInterface
    {
        $result                     = $this->parseOperand($tokens);

        if ($result instanceof ConstantInterface) {
            return $result;
        }

        throw new ParseException('Expected constant expression');
    }
}
