<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\Exceptions\NodeException;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where as WhereNode;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Dsl\Sql\Query\Subquery as SubqueryNode;
use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleInterface;
use IfCastle\Exceptions\RequiredValueEmpty;
use IfCastle\Exceptions\UnexpectedValue;

class Subquery extends Select
{
    /**
     * @throws UnexpectedValue
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): SubqueryInterface
    {
        $tokens->increaseRecursionDepth();

        $isBrackets                 = false;

        if ($tokens->currentTokenAsString() === '(') {
            $isBrackets             = true;
            $tokens->nextTokens();
        }

        $result                     = parent::parseTokens($tokens);

        if ($isBrackets && $tokens->currentTokenAsString() !== ')') {
            throw new ParseException(
                'Closing bracket expected for Subquery', ['line' => $tokens->getCurrentLine()]
            );
        }

        if ($isBrackets) {
            $tokens->nextTokens();
        }

        $tokens->decreaseRecursionDepth();

        if ($result instanceof SubqueryInterface) {
            return $result;
        }

        throw new UnexpectedValue('$result', $result, SubqueryInterface::class);
    }

    /**
     * Transform expression:
     * THE EntityName
     * to
     * SELECT @id FROM EntityName
     *
     * Using for expression:
     * SELECT * FROM Entity1 WHERE id IN THE Entity2
     *
     * @param   TokensIterator      $tokens
     *
     * @throws  ParseException
     */
    public function parseTheExpression(TokensIteratorInterface $tokens): SubqueryInterface
    {
        // Get entity name
        [$type, $token, $line]      = $tokens->currentToken();

        if ($type !== T_STRING) {
            throw new ParseException(
                \sprintf('Expected EntityName for expression {THE \'Entity\'} (got %s)', $token), ['line' => $line]
            );
        }

        $tokens->nextTokens();

        return (new static())->parse(/** @lang aql */ 'SELECT @id FROM ' . $token);
    }

    /**
     * @throws RequiredValueEmpty
     * @throws NodeException
     */
    #[\Override]
    protected function newSelectNode(string|JoinInterface                     $from,
        array|TupleInterface|null                $tuple = null,
        ConditionsInterface|array|WhereNode|null $where = null,
        ?LimitInterface                           $limit = null
    ): QueryInterface {
        return new SubqueryNode($from, $tuple, $where, $limit);
    }
}
