<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\WhereEntity as WhereEntityNode;

class WhereEntity extends AqlParserAbstract
{
    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): NodeInterface
    {
        $firstToken                 = $tokens->currentTokenAsString();
        $isExclude                  = false;

        if ($firstToken !== WhereEntityNode::ENTITY) {
            throw new ParseException("Expected key word 'ENTITY'");
        }

        [$type, $token, $line]      = $tokens->nextToken();

        if ($tokens->currentTokenAsString() === WhereEntityNode::EXCLUDE) {
            $isExclude              = true;
            [$type, $token, $line]  = $tokens->nextToken();
        }

        if ($type !== T_STRING) {
            throw new ParseException(
                'Expected entity name (T_STRING)' . \sprintf(' (got \'%s\')', $token),
                ['line' => $line]
            );
        }

        $entityName                 = $token;
        $conditions                 = null;

        $tokens->nextTokens();

        if ($tokens->currentTokenAsString() === '(') {

            $tokens->increaseRecursionDepth();
            $conditions             = new Conditions()->parseTokens($tokens->nextTokens());
            $tokens->decreaseRecursionDepth();

            if ($tokens->currentTokenAsString() !== ')') {
                throw new ParseException(
                    "Closing parenthesis expected ')' in the expression 'ENTITY entityName(...)' "
                    . "(got '{$tokens->currentTokenAsString()}')");
            }

            $tokens->nextTokens();
        }

        return new WhereEntityNode($entityName, $conditions, $isExclude);
    }
}
