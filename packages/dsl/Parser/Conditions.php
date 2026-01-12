<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Conditions\Conditions as ConditionsNode;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\JoinConditions;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperation;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\SingleOperation;
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
    protected function parseConditions(TokensIteratorInterface $tokens, array $conditions = [], string $typeConditions = ''): ConditionsInterface
    {
        $tokens->increaseRecursionDepth();

        $stopTokens                 = $tokens->getStopTokens();

        $line                       = null;
        $isSubConditions            = $conditions !== [];

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

            // Case when left side is sub-conditions
            if ($token === '(') {
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
                $left               = $this->parseOperand($tokens);
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

            if ($token === ConditionsInterface::TYPE_AND) {
                if ($isSubConditions && $typeConditions === ConditionsInterface::TYPE_OR) {
                    break;
                } elseif ($typeConditions === ConditionsInterface::TYPE_OR) {
                    $conditions[]       = $this->parseConditions($tokens->nextTokens(), [$condition], $token);
                } else {
                    $conditions[]       = $condition;
                }
            } elseif ($token === ConditionsInterface::TYPE_OR) {
                if ($typeConditions === ConditionsInterface::TYPE_AND) {
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
                    $right              = $this->parseOperand($tokens);
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
