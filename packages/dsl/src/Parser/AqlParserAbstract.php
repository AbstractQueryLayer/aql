<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantNull;
use IfCastle\AQL\Dsl\Sql\Parameter\Parameter;
use IfCastle\Exceptions\UnexpectedValue;

abstract class AqlParserAbstract implements ParserInterface
{
    /**
     * @throws ParseException
     */
    #[\Override]
    public function parse(string $code): NodeInterface
    {
        $tokens                     = new TokensIterator($code);

        $node                       = $this->parseTokens($tokens);

        $tokens->throwIfNotEnded();

        return $node;
    }

    /**
     * Parse expressions like: [EntityName.]Definition[()]
     * examples: property
     *           entityName.property
     *           function()
     *           entityName.function().
     *
     *
     * @throws ParseException|UnexpectedValue
     */
    protected function parseOperand(TokensIteratorInterface $tokens, bool $isAllowSubqueries = false): NodeInterface
    {
        [$type, $token, $line]  = $tokens->currentToken();

        // 1. Parameter case
        if ($token === '{') {

            [$type, $token, $line] = $tokens->nextToken();

            if ($type !== T_STRING) {
                throw new ParseException(
                    'Parameter: expected operand (T_STRING)' . \sprintf(' (got \'%s\')', $token),
                    ['line' => $line]
                );
            }

            $parameter              = new Parameter($token);

            [, $token, $line]  = $tokens->nextToken();

            if ($token !== '}') {
                throw new ParseException(
                    'Parameter: expected "}"' . \sprintf(' (got \'%s\')', $token),
                    ['line' => $line]
                );
            }

            return $parameter;
        }

        // 2. Case Subquery
        if ($token === '(') {

            if ($isAllowSubqueries === false) {
                throw new ParseException('Subqueries are not allowed for this operand', ['line' => $line]);
            }

            return (new Subquery())->parseTokens($tokens);
        }

        // 3. Case1 constant "string" or 'string'
        if ($type === T_CONSTANT_ENCAPSED_STRING) {
            $tokens->nextToken();
            return new Constant(\substr((string) $token, 1, -1));
        }

        // 4.1. case for numbers
        $unaryOperation             = null;

        // Unary plus or minus
        if ($token === '+' || $token === '-') {
            $unaryOperation         = $token;
            [$type, $token, $line]  = $tokens->nextToken();
        }

        if ($type === T_LNUMBER) {
            $tokens->nextToken();
            // Integer
            return new Constant((int) $token * ($unaryOperation === '-' ? -1 : 1));
        }

        if ($type === T_DNUMBER) {
            $tokens->nextToken();
            // float
            return new Constant((float) $token * ($unaryOperation === '-' ? -1.0 : 1.0));
        }

        if ($unaryOperation !== null) {
            throw new ParseException(
                \sprintf('After unary operation \'%s\' expected number, got \'%s\'', $unaryOperation, $token),
                ['line' => $line]
            );
        }

        $isNamedField               = false;

        // 4.2. Special case for named-properties
        if ($token === '@') {
            $isNamedField           = true;
            [$type, $token, $line]  = $tokens->nextToken();
        }

        if ($type !== T_STRING) {
            throw new ParseException(
                'Expected operand string or "string"' . " (got '$token')",
                ['line' => $line]
            );
        }

        if (false === $isNamedField) {
            // 4.3. Special case for TRUE, FALSE and NULL
            switch (\strtoupper((string) $token)) {
                case 'TRUE':        $tokens->nextToken();
                    return new Constant(true);
                case 'FALSE':       $tokens->nextToken();
                    return new Constant(false);
                case 'NULL':        $tokens->nextToken();
                    return new ConstantNull();
            }
        }

        $operand                    = $token;
        $entityName                 = null;

        [, $token,]                 = $tokens->nextToken();

        if ($token === '.') {
            // 3. Case expression: Entity.Property or Entity.Function
            [$type, $token, $line]  = $tokens->nextToken();

            if ($type !== T_STRING) {
                throw new ParseException(
                    'Expected operand string in String.String expression' . " (got '$token')",
                    ['line' => $line]
                );
            }

            $entityName             = $operand;
            $operand                = $token;

            [, $token, ]            = $tokens->nextToken();
        }

        if ($isNamedField) {
            $operand                = '@' . $operand;
        }

        // 4. Case Function?
        if ($token === '(') {
            return (new FunctionReference())->parseParameters($tokens, $operand, $entityName);
        }

        return new Column($operand, $entityName);

    }

    /**
     * @throws ParseException
     */
    public function parseColumn(TokensIteratorInterface $tokens): ColumnInterface
    {
        $result                     = $this->parseOperand($tokens);

        if ($result instanceof ColumnInterface) {
            return $result;
        }

        throw new ParseException('Expected FieldRef expression');
    }

    /**
     * @throws ParseException
     */
    public function parseAlias(TokensIteratorInterface $tokens): string
    {
        $alias                      = '';

        if ($tokens->currentTokenAsString() === 'AS') {
            // define alias
            [$type, $token, $line] = $tokens->nextToken();

            $alias                  = match (true) {
                $type === T_CONSTANT_ENCAPSED_STRING => \substr((string) $token, 1, -1),
                $type === T_STRING => $token,
                default => throw new ParseException(\sprintf('Expected alias for columns (got \'%s\')', $token), ['line' => $line]),
            };

            $tokens->nextToken();
        }

        return $alias;
    }

    /**
     * Try to parse Columns expression or return NULL.
     * Column expression should be like:
     * (column1, column2, column3, ...).
     *
     * @throws ParseException
     */
    public function tryToParseColumns(TokensIteratorInterface $tokens): ?array
    {
        if ($tokens->currentTokenAsString() !== '(') {
            return null;
        }

        $tokens->nextToken();

        $stopTokens                 = $tokens->getStopTokens();
        $columns                    = [];

        while (true) {
            if (\array_key_exists($tokens->currentTokenAsString(), $stopTokens)) {
                break;
            }

            $columns[]               = $this->parseColumn($tokens);

            if ($tokens->currentTokenAsString() !== ',') {
                break;
            }

            $tokens->nextTokens();
        }

        if ($tokens->currentTokenAsString() !== ')') {
            throw new ParseException(
                'Expected ")", got: ' . $tokens->currentTokenAsString(),
                ['line' => $tokens->getCurrentLine()]
            );
        }

        $tokens->nextTokens();

        return $columns;
    }

    protected function exceptStringToken(TokensIterator $tokens): string
    {
        [$type, $token, $line]          = $tokens->currentToken();

        $result                         = match ($type) {
            T_CONSTANT_ENCAPSED_STRING  => \substr((string) $token, 1, -1),
            T_STRING                    => $token,
            default                     => throw new ParseException(
                "Expected string token (got '$token')", ['line' => $line]
            ),
        };

        $tokens->nextToken();

        return $result;
    }
}
