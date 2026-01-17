<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Ddl\Parser;

use IfCastle\AQL\Dsl\Ddl\ColumnDefinition as ColumnDefinitionNode;
use IfCastle\AQL\Dsl\Ddl\ColumnDefinitionInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Parser\TokensIteratorInterface;
use IfCastle\AQL\Dsl\Sql\RawSql;

class ColumnDefinition extends DdlParserAbstract
{
    /**
     * @throws ParseException
     */
    public static function parseColumnName(TokensIteratorInterface $tokens, bool $isThrow = true): ?string
    {
        [$type, $token, $line]      = $tokens->currentToken();

        $isQuote                    = false;

        if ($token === '`') {
            $isQuote                = true;
            [$type, $token, $line]  = $tokens->nextToken();
        }

        if ($type !== T_ENCAPSED_AND_WHITESPACE && $type !== T_STRING) {

            if (!$isThrow && !$isQuote) {
                return null;
            }

            throw new ParseException('Expected column name ' . \sprintf('(got \'%s\')', $token), ['line' => $line]);
        }

        $tokens->nextTokens();

        if ($isQuote) {

            if ($tokens->currentTokenAsString() !== '`') {
                throw new ParseException('Closing quote "`" expected ' . \sprintf('(got \'%s\')', $token), ['line' => $line]);
            }

            $tokens->nextTokens();
        }

        return $token;
    }

    /**
     * Parse expression: (key_part,...).
     *
     *
     * @throws ParseException
     */
    public static function parseColumnsList(TokensIteratorInterface $tokens): array
    {
        if ($tokens->currentTokenAsString() !== '(') {
            return [];
        }

        $columns                    = [];

        // (key_part,...)
        $tokens->nextTokens();

        while ($tokens->valid() && $tokens->currentTokenAsString() !== ')') {

            $columns[]              = static::parseColumnName($tokens);

            if ($tokens->currentTokenAsString() === ')') {
                $tokens->nextTokens();
                break;
            }

            if ($tokens->currentTokenAsString() !== ',') {
                throw new ParseException('Delimiter "," expected ' . "(got '{$tokens->currentTokenAsString()}')");
            }

            $tokens->nextTokens();
        }

        return $columns;
    }

    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): ColumnDefinitionInterface
    {
        // see: https://dev.mysql.com/doc/refman/8.0/en/create-table.html
        //
        //        column_definition: {
        //        data_type [NOT NULL | NULL] [DEFAULT {literal | (expr)} ]
        //      [VISIBLE | INVISIBLE]
        //      [AUTO_INCREMENT] [UNIQUE [KEY]] [[PRIMARY] KEY]
        //      [COMMENT 'string']
        //      [COLLATE collation_name]
        //      [COLUMN_FORMAT {FIXED | DYNAMIC | DEFAULT}]
        //      [ENGINE_ATTRIBUTE [=] 'string']
        //      [SECONDARY_ENGINE_ATTRIBUTE [=] 'string']
        //      [STORAGE {DISK | MEMORY}]
        //      [reference_definition]
        //      [check_constraint_definition]
        //      | data_type
        //      [COLLATE collation_name]
        //      [GENERATED ALWAYS] AS (expr)
        //        [VIRTUAL | STORED] [NOT NULL | NULL]
        //      [VISIBLE | INVISIBLE]
        //      [UNIQUE [KEY]] [[PRIMARY] KEY]
        //      [COMMENT 'string']
        //      [reference_definition]
        //      [check_constraint_definition]

        $columnName                 = static::parseColumnName($tokens);

        // see: https://dev.mysql.com/doc/refman/8.0/en/data-types.html
        [$type, $token, $line]      = $tokens->currentToken();

        if ($type !== T_ENCAPSED_AND_WHITESPACE && $type !== T_STRING) {
            throw new ParseException('Expected column type name ' . "(got '$token')", ['line' => $line]);
        }

        $columnType                 = \strtoupper((string) $token);
        $columnLine                 = $line;
        $maximumDisplayWidth        = null;
        $isAutoIncrement            = false;
        $isUnsigned                 = false;
        $isZerofill                 = false;
        $isNull                     = true;
        $defaultValue               = null;
        $digitsNumber               = null;
        $variants                   = null;
        $comment                    = null;

        $tokens->nextTokens();

        switch ($columnType) {

            // Numbers
            case 'BOOL':
            case 'BOOLEAN':
                break;
            case 'BIT':
            case 'TINYINT':
            case 'SMALLINT':
            case 'MEDIUMINT':
            case 'INT':
            case 'INTEGER':
            case 'BIGINT':

                // maximumDisplayWidth expression like: INT(5)
                if ($tokens->currentTokenAsString() === '(') {

                    [$type, $token, $line] = $tokens->nextToken();

                    if ($type !== T_LNUMBER) {
                        throw new ParseException("Expected maximum display width (NUMBER) for type $type" . "(got '$token')", ['line' => $line]);
                    }

                    $maximumDisplayWidth = (int) $token;

                    $tokens->nextTokens();

                    if ($tokens->currentTokenAsString() !== ')') {
                        throw new ParseException(
                            'Expected closed bracket ")" for maximum display width ' . "(got '{$tokens->currentTokenAsString()}')"
                        );
                    }

                    $tokens->nextTokens();
                }

                if ($tokens->currentTokenAsString() === 'UNSIGNED') {
                    $isUnsigned     = true;
                    $tokens->nextTokens();
                }

                if ($tokens->currentTokenAsString() === 'ZEROFILL') {
                    // According to documentation:
                    // If you specify ZEROFILL for a numeric column,
                    // MySQL automatically adds the UNSIGNED attribute to the column.
                    $isUnsigned     = true;
                    $isZerofill     = true;
                    $tokens->nextTokens();
                }

                break;

            case 'DECIMAL':
            case 'DEC':
            case 'FLOAT':
            case 'DOUBLE':
            case 'REAL':

                // maximumDisplayWidth expression like: FLOAT(5,6)
                if ($tokens->currentTokenAsString() === '(') {

                    [$type, $token, $line] = $tokens->nextToken();

                    if ($type !== T_LNUMBER) {
                        throw new ParseException("Expected maximum display width (NUMBER) for type $type" . "(got '$token')", ['line' => $line]);
                    }

                    $maximumDisplayWidth = (int) $token;

                    $tokens->nextTokens();

                    if ($tokens->currentTokenAsString() !== ',') {

                        [$type, $token, $line] = $tokens->nextToken();

                        if ($type !== T_LNUMBER) {
                            throw new ParseException("Expected digits number for type $type" . "(got '$token')", ['line' => $line]);
                        }

                        $digitsNumber   = (int) $token;

                        $tokens->nextTokens();
                    }

                    if ($tokens->currentTokenAsString() !== ')') {
                        throw new ParseException(
                            'Expected closed bracket ")" for maximum display width ' . "(got '{$tokens->currentTokenAsString()}')"
                        );
                    }

                    $tokens->nextTokens();
                }

                if ($tokens->currentTokenAsString() === 'UNSIGNED') {
                    $isUnsigned     = true;
                    $tokens->nextTokens();
                }

                if ($tokens->currentTokenAsString() === 'ZEROFILL') {
                    // According to documentation:
                    // If you specify ZEROFILL for a numeric column,
                    // MySQL automatically adds the UNSIGNED attribute to the column.
                    $isUnsigned     = true;
                    $isZerofill     = true;
                    $tokens->nextTokens();
                }

                break;

                // Date and time
            case 'DATETIME':
            case 'TIMESTAMP':
            case 'TIME':
                // fractional seconds
                if ($tokens->currentTokenAsString() === '(') {

                    [$type, $token, $line] = $tokens->nextToken();

                    if ($type !== T_LNUMBER) {
                        throw new ParseException("Expected fractional seconds (NUMBER) for type $type" . "(got '$token')", ['line' => $line]);
                    }

                    $maximumDisplayWidth = (int) $token;

                    $tokens->nextTokens();

                    if ($tokens->currentTokenAsString() !== ')') {
                        throw new ParseException(
                            'Expected closed bracket ")" for fractional seconds ' . "(got '{$tokens->currentTokenAsString()}')"
                        );
                    }

                    $tokens->nextTokens();
                }

                break;

            case 'DATE':
            case 'YEAR':

                break;

                // String data types
            case 'CHAR':
            case 'VARCHAR':
            case 'BINARY':
            case 'VARBINARY':

                if ($tokens->currentTokenAsString() === '(') {

                    [$type, $token, $line] = $tokens->nextToken();

                    if ($type !== T_LNUMBER) {
                        throw new ParseException("Expected characters number (NUMBER) for type $type" . "(got '$token')", ['line' => $line]);
                    }

                    $maximumDisplayWidth = (int) $token;

                    $tokens->nextTokens();

                    if ($tokens->currentTokenAsString() !== ')') {
                        throw new ParseException(
                            'Expected closed bracket ")" for characters number ' . "(got '{$tokens->currentTokenAsString()}')"
                        );
                    }

                    $tokens->nextTokens();
                }

                break;


            case 'BLOB':
            case 'TEXT':

            case 'MEDIUMBLOB':
            case 'MEDIUMTEXT':
            case 'LONGBLOB':
            case 'LONGTEXT':

                break;

            case 'ENUM':
            case 'SET':

                // Parse ENUM and SET
                if ($tokens->currentTokenAsString() !== '(') {
                    throw new ParseException(
                        'Expected ENUM or SET values list ' . "(got '{$tokens->currentTokenAsString()}')"
                    );
                }

                $tokens->nextTokens();
                $variants           = [];

                while ($tokens->valid() && $tokens->currentTokenAsString() !== ')') {

                    [$type, $token] = $tokens->currentToken();

                    if (\in_array($type, [T_LNUMBER, T_DNUMBER])) {
                        $value      = $type === T_LNUMBER ? (int) $token : (float) $token;
                    } elseif ($type == T_STRING) {
                        $value      = new RawSql($token);
                    } elseif ($type === T_CONSTANT_ENCAPSED_STRING) {
                        $value      = \substr((string) $token, 1, -1);
                    } else {
                        throw new ParseException(
                            'Expected variant value ' . "(got '{$tokens->currentTokenAsString()}')"
                        );
                    }

                    $variants[]     = $value;

                    $tokens->nextTokens();

                    if ($tokens->currentTokenAsString() !== ',') {
                        break;
                    }

                    $tokens->nextTokens();
                }

                if ($tokens->currentTokenAsString() !== ')') {
                    throw new ParseException(
                        'Expected ENUM or SET values list end brace' . "(got '{$tokens->currentTokenAsString()}')"
                    );
                }

                $tokens->nextTokens();

                break;

            case 'JSON':

            default:
        }

        // CHARACTER SET utf8 COLLATE utf8_general_ci
        if ($tokens->currentTokenAsString() === 'CHARACTER') {

            $tokens->nextTokens();

            if ($tokens->currentTokenAsString() !== 'SET') {
                throw new ParseException(
                    "Expected CHARACTER SET for type  $columnType on line $columnLine " . "(got '{$tokens->currentTokenAsString()}')"
                );
            }

            $tokens->nextTokens();
            $tokens->nextTokens();

            if ($tokens->currentTokenAsString() !== 'COLLATE') {
                throw new ParseException(
                    "Expected COLLATE for type  $columnType on line $columnLine " . "(got '{$tokens->currentTokenAsString()}')"
                );
            }

            $tokens->nextTokens();
            $tokens->nextTokens();
        }

        // [NOT NULL | NULL]
        if ($tokens->currentTokenAsString() === 'NOT') {
            $isNull                 = false;
            $tokens->nextTokens();

            if ($tokens->currentTokenAsString() !== 'NULL') {
                throw new ParseException(
                    "Expected NULL for type  $columnType on line $columnLine " . "(got '{$tokens->currentTokenAsString()}')"
                );
            }

            $tokens->nextTokens();
        } elseif ($tokens->currentTokenAsString() === 'NULL') {
            $isNull                 = true;
            $tokens->nextTokens();
        }

        // DEFAULT
        // see: https://dev.mysql.com/doc/refman/8.0/en/data-type-defaults.html
        if ($tokens->currentTokenAsString() === 'DEFAULT') {

            [$type, $token, $line]  = $tokens->nextToken();

            if (\in_array($type, [T_LNUMBER, T_DNUMBER])) {
                $defaultValue       = $type === T_LNUMBER ? (int) $token : (float) $token;
            } elseif ($type == T_STRING) {
                $defaultValue       = new RawSql($token);
            } elseif ($type === T_CONSTANT_ENCAPSED_STRING) {
                $defaultValue       = \substr((string) $token, 1, -1);
            }

            $tokens->nextTokens();
        }

        $openedBracket          = 0;
        $stopTokens             = $tokens->getStopTokens();
        $stopTokens[',']        = true;

        // NOT SUPPORTED OPTIONS
        while ($tokens->valid()) {

            if ($tokens->currentTokenAsString() === '(') {
                $openedBracket++;
            } elseif ($tokens->currentTokenAsString() === ')') {

                if ($openedBracket === 0) {
                    break;
                }

                $openedBracket--;
            } elseif ($tokens->currentTokenAsString() === 'AUTO_INCREMENT') {
                $isAutoIncrement = true;
            } elseif ($tokens->currentTokenAsString() === 'COMMENT' && $openedBracket === 0) {

                [$type, $token]     = $tokens->nextToken();

                if ($type !== T_CONSTANT_ENCAPSED_STRING) {
                    throw new ParseException(
                        "Expected COMMENT string for type  $columnType on line $columnLine" . "(got '{$tokens->currentTokenAsString()}')"
                    );
                }

                $comment            = \substr((string) $token, 1, -1);

            } elseif ($openedBracket === 0 && \array_key_exists($tokens->currentTokenAsString(true), $stopTokens)) {
                break;
            }

            $tokens->nextTokens();
        }

        if ($openedBracket > 0) {
            throw new ParseException("Unclosed parenthesis found for column type '$columnType' on line '$columnLine'");
        }

        return new ColumnDefinitionNode(
            $columnName,
            $columnType,
            $maximumDisplayWidth,
            $digitsNumber,
            $isUnsigned,
            $isNull,
            $isZerofill,
            $isAutoIncrement,
            $defaultValue,
            $variants,
            $comment
        );
    }

}
