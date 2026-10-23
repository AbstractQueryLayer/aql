<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Parser\Constant as ConstantParser;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\Conditions as ConditionsNode;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\JoinConditions;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperation;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\SingleOperation;
use IfCastle\AQL\Dsl\Sql\Query\Expression\ValueList;
use IfCastle\AQL\Dsl\Sql\Query\Expression\WhereEntity as WhereEntityNode;

class Conditions extends AqlParserAbstract
{
    protected bool $withBrackets    = false;

    protected bool $asJoinConditions = false;

    public function withBrackets(): static
    {
        $this->withBrackets          = true;

        return $this;
    }

    public function asJoinConditions(): static
    {
        $this->asJoinConditions      = true;

        return $this;
    }

    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): ConditionsInterface
    {
        if (false === $this->withBrackets) {
            return $this->parseConditions($tokens);
        }

        if ($tokens->currentTokenAsString() !== '(') {
            throw new ParseException('Expected open bracket "(" for Conditions');
        }

        $conditions                 = $this->parseConditions($tokens->nextTokens());

        if ($tokens->currentTokenAsString() !== ')') {
            throw new ParseException('Expected close bracket ")" for Conditions');
        }

        $tokens->nextToken();

        return $conditions;
    }

    /**
     * @throws ParseException
     */
    protected function parseConditions(TokensIteratorInterface $tokens): ConditionsInterface
    {
        $tokens->increaseRecursionDepth();

        $stopTokens                 = $tokens->getStopTokens();

        $line                       = null;
        $conditions                 = [];
        $typeConditions             = '';

        while (true) {

            $token                  = $tokens->currentTokenAsString();

            // expected expressions:
            // (sub-conditions)
            // left operator right
            // left()
            // operand comparison_operator ANY (subquery)
            // operand IN (subquery)
            // operand NOT IN (subquery)
            // operand comparison_operator SOME (subquery)
            // operand comparison_operator ALL (subquery)

            // Case when left side is a row of columns: (a, b) IN (...)
            if ($tokens->isParenthesisedList()) {
                $condition          = $this->parseRowIn($tokens);
            } elseif ($token === '(') {
                // Case when left side is sub-conditions
                // sub conditions case
                $condition          = $this->parseConditions($tokens->nextTokens());

                if ($tokens->currentTokenAsString() !== ')') {
                    throw new ParseException('Expected close bracket ")"', ['line' => $line]);
                }

                $tokens->nextTokens();

            } elseif ($token === WhereEntityNode::ENTITY) {
                // special expression Entity
                $condition          = (new WhereEntity())->parseTokens($tokens);
            } else {
                // LR operation case
                $left               = $this->parseExpression($tokens);
                [$operation, $right] = $this->parseRight($tokens);

                if ($operation === null && $right === null) {
                    $condition      = new SingleOperation($left);
                } else {
                    $condition      = new LROperation($left, $operation, $right);
                }
            }

            // Look logical AND or OR operator
            [, $token, $line]   = $tokens->currentToken();

            if (\array_key_exists($token, $stopTokens)) {
                $conditions[]       = $condition;
                break;
            }

            if ($token === ')') {
                $conditions[]       = $condition;
                break;
            }

            $token                  = \strtoupper((string) $token);

            if ($token === ConditionsInterface::TYPE_AND || $token === ConditionsInterface::TYPE_OR) {
                // AQL gives AND no precedence over OR: a group mixing them needs brackets to say which is meant.
                if ($typeConditions !== '' && $typeConditions !== $token) {
                    throw new ParseException('Prohibited use AND with OR without bracket', ['line' => $line]);
                }

                $conditions[]       = $condition;
            } else {
                $conditions[]           = $condition;
                break;
            }

            if ($typeConditions === '') {
                $typeConditions         = $token;
            }

            if (!$tokens->valid()) {
                break;
            }

            $tokens->nextToken();
        }

        if ($typeConditions === '') {
            $typeConditions         = ConditionsInterface::TYPE_AND;
        }

        $expression                 = $this->newConditionsNode($typeConditions);

        foreach ($conditions as $condition) {
            $expression->add($condition);
        }

        $tokens->decreaseRecursionDepth();

        return $expression;
    }

    /**
     * Parses `(column, ...) [NOT] IN ((value, ...), ...)`: a row matches when it equals one of the rows of values.
     *
     * @throws ParseException when the row holds anything but columns, IN is missing, or a row of values has another
     *     width
     */
    protected function parseRowIn(TokensIteratorInterface $tokens): ValueList
    {
        // A delimiter has no line: take the line of the first column.
        $line                       = $tokens->nextTokens()->getCurrentLine();
        $columns                    = [];

        do {
            $column                 = $this->parseOperand($tokens);

            if (false === $column instanceof ColumnInterface) {
                throw new ParseException('A row before IN lists columns', ['line' => $line]);
            }

            $columns[]              = $column;
        } while ($tokens->checkAndConsumeToken(','));

        $tokens->assertTokenIs(')');
        $valueList                  = new ValueList(...$columns);

        if ($tokens->checkAndConsumeToken('NOT') && $tokens->checkAndConsumeToken('IN')) {
            $valueList->asNotIn();
        } elseif ($tokens->checkAndConsumeToken('IN')) {
            $valueList->asIn();
        } else {
            throw new ParseException('Expected IN or NOT IN after a row of columns', ['line' => $line]);
        }

        $tokens->assertTokenIs('(');

        do {
            $values                 = $this->parseValueRow($tokens);

            if (\count($values) !== \count($columns)) {
                throw new ParseException(
                    \sprintf('A row of %d columns got a row of %d values', \count($columns), \count($values)),
                    ['line' => $line]
                );
            }

            $valueList->appendValueList($values);
        } while ($tokens->checkAndConsumeToken(','));

        $tokens->assertTokenIs(')');

        return $valueList;
    }

    /**
     * @return list<NodeInterface> the constants and parameters of one `(value, ...)`
     * @throws ParseException
     */
    protected function parseValueRow(TokensIteratorInterface $tokens): array
    {
        $tokens->assertTokenIs('(');
        $values                     = [];

        do {
            $values[]               = (new ConstantParser())->parseTokens($tokens);
        } while ($tokens->checkAndConsumeToken(','));

        $tokens->assertTokenIs(')');

        return $values;
    }

    /**
     * The operands of $conditions as children of an AND clause such as WHERE or HAVING: the children themselves for
     * an AND group, the group as the only child for an OR group.
     *
     * @return NodeInterface[]
     */
    protected function conjuncts(ConditionsInterface $conditions): array
    {
        if ($conditions instanceof ConditionsNode
            && $conditions->getType() === ConditionsInterface::TYPE_OR
            && $conditions->countOfOperations() > 1) {
            return [$conditions];
        }

        return $conditions->getChildNodes();
    }

    protected function newConditionsNode(string $typeConditions): ConditionsInterface
    {
        if ($this->asJoinConditions) {
            return new JoinConditions($typeConditions);
        }

        return new ConditionsNode($typeConditions);
    }

    /**
     * @throws ParseException
     */
    protected function parseRight(TokensIteratorInterface $tokens): array
    {
        $operation                              = null;
        $right                                  = null;

        // see: https://dev.mysql.com/doc/refman/8.0/en/func-op-summary-ref.html
        switch ($tokens->currentTokenAsString()) {
            case 'NOT':
                [, $token, $line]           = $tokens->nextToken();

                switch (\strtoupper((string) $token)) {
                    case 'LIKE':
                        $operation              = 'NOT ' . $token;
                        $right                  = $this->parseOperand($tokens->nextTokens());
                        break;
                    case 'BETWEEN':
                        $operation              = 'NOT';
                        $right                  = (new Between())->parseTokens($tokens);
                        break;
                    case 'IN':
                        $operation              = 'NOT IN';
                        $right                  = (new InExpression())->parseTokens($tokens->nextTokens());
                        break;

                    default:
                        throw new ParseException(
                            'Expected LIKE, BETWEEN OR IN' . \sprintf(' (got \'%s\')', $token), ['line' => $line]
                        );
                }

                break;

            case 'IS':
                [, $token, $line] = $tokens->nextToken();

                $operation                      = 'IS';

                switch (\strtoupper((string) $token)) {
                    case 'NOT':
                        $operation              = 'IS NOT';

                        [, $token, $line]   = $tokens->nextToken();

                        $right                  = match (\strtoupper((string) $token)) {
                            'NULL'              => new Constant(null),
                            'TRUE'              => new Constant(true),
                            'FALSE'             => new Constant(false),
                            default             => throw new ParseException(
                                \sprintf('Expected boolean expression IS NOT (got: \'%s\')', $token), ['line' => $line]
                            ),
                        };

                        break;
                    case 'NULL':
                        $right                  = new Constant(null);
                        break;
                    case 'TRUE':
                        $right                  = new Constant(true);
                        break;
                    case 'FALSE':
                        $right                  = new Constant(false);
                        break;
                    default:
                        throw new ParseException(
                            \sprintf('Expected boolean expression IS (got: \'%s\')', $token), ['line' => $line]
                        );
                }

                $tokens->nextTokens();

                break;
            case '=':
            case '>':
            case '<':
            case '>=':
            case '<=':
            case '<>':
            case '!=':
                $operation              = $tokens->currentTokenAsString();
                // support subqueries
                // see: https://dev.mysql.com/doc/refman/8.0/en/any-in-some-subqueries.html
                // https://dev.mysql.com/doc/refman/8.0/en/all-subqueries.html
                if (\in_array($tokens->nextTokens()->currentTokenAsString(), ['ANY', 'SOME', 'ALL'], true)) {
                    $right              = (new Subquery())->parseTokens($tokens);
                } else {
                    $right              = $this->parseExpression($tokens);
                }

                break;
            case '<=>':
            case 'LIKE':
                $operation              = $tokens->currentTokenAsString();
                $right                  = $this->parseOperand($tokens->nextTokens());
                break;
            case 'BETWEEN':
                $operation              = '';
                $right                  = (new Between())->parseTokens($tokens);
                break;
            case 'IN':
                $operation              = 'IN';
                $right                  = (new InExpression())->parseTokens($tokens->nextTokens());
                break;
            default:
                // Singe operation
        }

        return [$operation, $right];
    }
}
