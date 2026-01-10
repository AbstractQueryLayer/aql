<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Ddl\Parser;

use IfCastle\AQL\Dsl\Ddl\ConstraintDefinition;
use IfCastle\AQL\Dsl\Ddl\ConstraintDefinitionInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Parser\TokensIteratorInterface;

class Constraint
{
    public static function isConstraintStart(TokensIteratorInterface $tokens): bool
    {
        return match ($tokens->currentTokenAsString()) {
            'CONSTRAINT'    => true,
            default         => false,
        };
    }

    /**
     * @throws ParseException
     */
    public function parseTokens(TokensIteratorInterface $tokens): ConstraintDefinitionInterface
    {
        if ($tokens->currentTokenAsString() !== 'CONSTRAINT') {
            throw new ParseException('Expected keyword CONSTRAINT' . "(got '{$tokens->currentTokenAsString()}')");
        }

        $tokens->nextTokens();

        //
        // In current version we parsed only
        // | [CONSTRAINT [symbol]] FOREIGN KEY
        //
        $constraintName             = ColumnDefinition::parseColumnName($tokens);

        if ($tokens->currentTokenAsString() !== 'FOREIGN') {
            $tokens->nextTokens();
            while ($tokens->valid() && $tokens->currentTokenAsString() !== ',') {
                $tokens->nextTokens();
            }

            return new ConstraintDefinition([], '', [], [], $constraintName);
        }

        $tokens->nextTokens();

        if ($tokens->currentTokenAsString() !== 'KEY') {
            throw new ParseException('Expected keyword KEY' . "(got '{$tokens->currentTokenAsString()}')");
        }

        $tokens->nextTokens();
        $indexName                  = ColumnDefinition::parseColumnName($tokens, false);

        // expression (col_name, ...)
        $columns                    = ColumnDefinition::parseColumnsList($tokens);

        // REFERENCES tbl_name (col_name,...)
        if ($tokens->currentTokenAsString() !== 'REFERENCES') {
            throw new ParseException('Expected keyword REFERENCES' . "(got '{$tokens->currentTokenAsString()}')");
        }

        $tokens->nextTokens();

        $tableName                  = ColumnDefinition::parseColumnName($tokens);
        $referenceColumns           = ColumnDefinition::parseColumnsList($tokens);

        // [ON DELETE reference_option]
        // [ON UPDATE reference_option]

        $referenceActions           = [];

        while ($tokens->valid()) {

            if ($tokens->currentTokenAsString() !== 'ON') {
                break;
            }

            $tokens->nextTokens();

            $action                 = $tokens->currentTokenAsString();

            if ($action !== 'DELETE' && $action !== 'UPDATE') {
                throw new ParseException('Expected keyword UPDATE or DELETE' . "(got '{$tokens->currentTokenAsString()}')");
            }

            $tokens->nextTokens();

            // reference_option:
            // RESTRICT | CASCADE | SET NULL | NO ACTION | SET DEFAULT
            $referenceOption        = $tokens->currentTokenAsString();

            $tokens->nextTokens();

            if ($referenceOption === 'SET') {
                if (\in_array($tokens->currentTokenAsString(), ['NULL', 'DEFAULT'])) {
                    $referenceOption .= ' ' . $tokens->currentTokenAsString();
                    $tokens->nextTokens();
                } else {
                    throw new ParseException('Expected keyword NULL or DEFAULT' . "(got '{$tokens->currentTokenAsString()}')");
                }
            } elseif ($referenceOption === 'NO') {
                if ($tokens->currentTokenAsString() !== 'ACTION') {
                    throw new ParseException('Expected keyword ACTION' . "(got '{$tokens->currentTokenAsString()}')");
                }

                $referenceOption    .= ' ACTION';
                $tokens->nextTokens();
            } elseif (!\in_array($referenceOption, ['RESTRICT', 'CASCADE'], true)) {
                throw new ParseException('Expected keyword RESTRICT or CASCADE' . "(got '{$tokens->currentTokenAsString()}')");
            }

            $referenceActions[$action] = $referenceOption;

            if (\in_array($tokens->currentTokenAsString(), [',', ')'])) {
                break;
            }
        }

        return new ConstraintDefinition($columns, $tableName, $referenceColumns, $referenceActions, $constraintName, $indexName);
    }
}
