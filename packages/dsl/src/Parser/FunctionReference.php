<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReference as FunctionReferenceNode;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperation;

/**
 * Parser for FunctionReferences.
 */
class FunctionReference extends AqlParserAbstract
{
    private const array COMPARISONS = ['=', '<', '>', '<=', '>=', '<>', '!='];

    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): FunctionReferenceInterface
    {
        $tokens->increaseRecursionDepth();

        [$type, $token, $line]      = $tokens->currentToken();

        // function
        $isNamed                    = false;

        // 3.1. Special case for named-properties
        if ($token === '@') {
            $isNamed                = true;
            [$type, $token, $line]  = $tokens->nextToken();
        }

        if ($type !== T_STRING) {
            throw new ParseException(
                'Expected operand (T_STRING or T_CONSTANT_ENCAPSED_STRING)' . \sprintf(' (got \'%s\')', $token),
                ['line' => $line]
            );
        }

        $operand                    = $token;
        $entityName                 = '';

        [, $token,]                 = $tokens->nextToken();

        if ($token === '.') {
            // 3. Case expression: Entity.Property or Entity.Function
            [$type, $token, $line]  = $tokens->nextToken();

            if ($type !== T_STRING) {
                throw new ParseException(
                    'Expected operand T_STRING in String.String expression' . \sprintf(' (got \'%s\')', $token),
                    ['line' => $line]
                );
            }

            $entityName             = $operand;
            $operand                = $token;

            [, $token, ]            = $tokens->nextToken();
        }

        if ($isNamed) {
            $operand                = '@' . $operand;
        }

        $tokens->decreaseRecursionDepth();

        // 4. Case Function?
        if ($token === '(') {
            return $this->parseParameters($tokens, $operand, $entityName);
        }

        throw new ParseException(
            'Expected function expression: [entity.]fun(...)', ['line' => $line, 'token' => $token]
        );
    }

    /**
     * @throws ParseException
     */
    public function parseParameters(TokensIterator $tokens, string $name, ?string $entityName = null): FunctionReferenceInterface
    {
        // Parsing parameters
        $stopTokens                 = $tokens->getStopTokens();
        $stopTokens[')']            = true;

        // Check opening brace
        if ($tokens->currentTokenAsString() !== '(') {
            throw new ParseException('Function parameters require an opening brace');
        }

        $tokens->nextTokens();

        $parameters                 = [];

        while ($tokens->valid() && !\array_key_exists(\strtolower($tokens->currentTokenAsString()), $stopTokens)) {

            $parameters[]           = $this->parseParameter($tokens);

            if ($tokens->currentTokenAsString() !== ',') {
                break;
            }

            $tokens->nextToken();
        }

        if ($tokens->currentTokenAsString() !== ')') {
            throw new ParseException('Function parameters require a closing brace');
        }

        $tokens->nextTokens();

        if (\str_starts_with($name, '@')) {
            $function               = new FunctionReferenceNode(\substr($name, 1), ...$parameters);
            $function->asVirtual();
        } else {
            $function               = new FunctionReferenceNode($name, ...$parameters);
        }

        if ($entityName !== null) {
            $function->setEntityName($entityName);
        }

        return $function;
    }

    /**
     * A function argument is a value expression, optionally compared with another one:
     * `IF(windowStart + INTERVAL 5 SECOND <= UTC_TIMESTAMP(6), 1, attempts + 1)`.
     *
     * @throws ParseException
     */
    private function parseParameter(TokensIteratorInterface $tokens): NodeInterface
    {
        $left                       = $this->parseExpression($tokens);
        $operation                  = $tokens->currentTokenAsString();

        if (false === \in_array($operation, self::COMPARISONS, true)) {
            return $left;
        }

        return new LROperation($left, $operation, $this->parseExpression($tokens->nextTokens()));
    }
}
