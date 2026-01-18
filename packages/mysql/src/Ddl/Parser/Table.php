<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Ddl\Parser;

use IfCastle\AQL\Dsl\Ddl\Table as TableNode;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Parser\TokensIteratorInterface;

class Table extends DdlParserAbstract
{
    /**
     *
     * @return static
     * @throws ParseException
     * @see: https://dev.mysql.com/doc/refman/8.0/en/create-table.html
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): TableNode
    {
        if ($tokens->currentTokenAsString() !== TableNode::ACTION_CREATE) {
            throw new ParseException('Expected keyword ' . TableNode::ACTION_CREATE);
        }

        [$tableName, $isTemporary]          = $this->parseTableName($tokens->nextTokens());
        [$columns, $indexes, $constraints]  = $this->parseCreateDefinitions($tokens);

        // We not parse options
        $comment                            = null;

        // Find the end ";"
        while ($tokens->valid() && $tokens->currentTokenAsString() !== ';') {

            if ($tokens->currentTokenAsString() === 'COMMENT') {

                $tokens->nextTokens();

                if ($tokens->currentTokenAsString() !== '=') {
                    throw new ParseException(
                        "Expected operator '=' after COMMENT keyword" . "(got '{$tokens->currentTokenAsString()}')"
                    );
                }

                [$type, $token, $line] = $tokens->nextToken();

                if ($type !== T_CONSTANT_ENCAPSED_STRING) {
                    throw new ParseException(
                        "Expected COMMENT string for table on line $line" . "(got '{$tokens->currentTokenAsString()}')"
                    );
                }

                $comment            = \substr((string) $token, 1, -1);
            }

            $tokens->nextTokens();
        }

        $table                      = new TableNode($tableName, $columns, $indexes, $constraints, $isTemporary ? ['temporary'] : []);

        if ($comment !== null) {
            $table->setComment($comment);
        }

        return $table;
    }

    /**
     * @throws ParseException
     */
    protected function parseTableName(TokensIteratorInterface $tokens): array
    {
        $isTemporary                = false;

        // see: https://dev.mysql.com/doc/refman/8.0/en/create-table.html
        // 1. CREATE [TEMPORARY] TABLE [IF NOT EXISTS] tbl_name

        if ($tokens->currentTokenAsString() === 'TEMPORARY') {
            $isTemporary            = true;

            $tokens->nextTokens();
        }

        if ($tokens->currentTokenAsString() !== 'TABLE') {
            throw new ParseException('Expected keyword TABLE, got: "' . $tokens->currentTokenAsString() . '"');
        }

        [$type, $token, $line]          = $tokens->nextToken();

        if ($token === 'IF') {

            [$type, $token, $line]      = $tokens->nextToken();

            if ($token !== 'NOT') {
                throw new ParseException('Expected IF NOT EXISTS ' . "(got '$token')", ['line' => $line]);
            }

            [$type, $token, $line]      = $tokens->nextToken();

            if ($token !== 'EXISTS') {
                throw new ParseException('Expected IF NOT EXISTS ' . "(got '$token')", ['line' => $line]);
            }

            [$type, $token, $line]      = $tokens->nextToken();
        }

        if ($token === '`') {

            [$type, $token, $line]      = $tokens->nextToken();

            $tokens->nextTokens();

            if ($tokens->currentTokenAsString() !== '`') {
                throw new ParseException('Closing quote expected, got: ' . $tokens->currentTokenAsString(), ['line' => $line]);
            }
        }

        if ($type !== T_ENCAPSED_AND_WHITESPACE) {
            throw new ParseException('Expected table name ' . "(got '$token')", ['line' => $line]);
        }

        $tokens->nextTokens();

        return [$token, $isTemporary];
    }

    /**
     * @throws ParseException
     */
    protected function parseCreateDefinitions(TokensIteratorInterface $tokens): array
    {
        if ($tokens->currentTokenAsString() !== '(') {
            throw new ParseException(
                'Expected start bracket "(" for create definitions ' . "(got '{$tokens->currentTokenAsString()}')"
            );
        }

        $tokens->nextTokens();

        $columns                    = [];
        $indexes                    = [];
        $constraints                = [];

        while ($tokens->valid() && $tokens->currentTokenAsString() !== ')') {

            if (IndexDefinition::isIndexStart($tokens)) {
                $indexes[]          = new IndexDefinition()->parseTokens($tokens);
            } elseif (Constraint::isConstraintStart($tokens)) {
                $constraints[]      = new Constraint()->parseTokens($tokens);
            } else {
                $columns[]          = new ColumnDefinition()->parseTokens($tokens);
            }

            if ($tokens->currentTokenAsString() !== ',') {
                break;
            }

            $tokens->nextTokens();
        }

        if ($tokens->currentTokenAsString() !== ')') {
            throw new ParseException(
                'Expected closed bracket ")" for create definitions ' . "(got '{$tokens->currentTokenAsString()}')"
            );
        }

        return [$columns, $indexes, $constraints];
    }
}
