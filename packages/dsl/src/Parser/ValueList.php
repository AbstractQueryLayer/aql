<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\ValueList as ValueListNode;
use IfCastle\AQL\Dsl\Sql\Query\Expression\ValueListInterface;

class ValueList extends AqlParserAbstract
{
    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): ValueListInterface
    {
        $columns                    = $this->tryToParseColumns($tokens);

        if ($columns === null || $columns === []) {
            throw new ParseException(
                'Expected "(" for start ValueList expression', ['line' => $tokens->getCurrentLine()]
            );
        }

        return $this->parseValues($tokens, $columns);
    }

    /**
     * @throws ParseException
     */
    public function parseValues(TokensIteratorInterface $tokens, array $columns): ValueListInterface
    {
        if ($tokens->currentTokenAsString() !== 'VALUES') {
            return new ValueListNode(...$columns);
        }

        $tokens->nextToken();
        $stopTokens                 = $tokens->getStopTokens();

        $valueList                  = new ValueListNode(...$columns);

        while (true) {
            if (\array_key_exists($tokens->currentTokenAsString(), $stopTokens)) {
                break;
            }

            if ($tokens->currentTokenAsString() !== '(') {
                break;
            }

            $tokens->nextToken();
            $values                     = [];

            while (true) {
                if (\array_key_exists($tokens->currentTokenAsString(), $stopTokens)) {
                    break;
                }

                $values[]               = new Constant()->parseTokens($tokens);

                if ($tokens->currentTokenAsString() !== ',' || $tokens->currentTokenAsString() === ')') {
                    break;
                } elseif ($tokens->valid()) {
                    $tokens->nextToken();
                }
            }

            $valueList->appendValueList($values);

            if ($tokens->currentTokenAsString() !== ')') {
                throw new ParseException('Expected ")"');
            }

            $tokens->nextToken();

            if ($tokens->currentTokenAsString() !== ',') {
                break;
            }

            $tokens->nextToken();
        }

        return $valueList;
    }
}
