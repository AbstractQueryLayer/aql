<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Ddl\Parser;

use IfCastle\AQL\Dsl\Ddl\PartitionBy as PartitionByNode;
use IfCastle\AQL\Dsl\Ddl\PartitionByInterface;
use IfCastle\AQL\Dsl\Ddl\PartitionDefinition;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Parser\FunctionReference;
use IfCastle\AQL\Dsl\Parser\TokensIteratorInterface;

/**
 * Parser for PARTITION BY clause.
 *
 * Supports MySQL 8.4 partition syntax:
 * - PARTITION BY [LINEAR] HASH(expr) [PARTITIONS num]
 * - PARTITION BY [LINEAR] KEY [ALGORITHM={1|2}] (column_list) [PARTITIONS num]
 * - PARTITION BY RANGE(expr) (partition_definition [, ...])
 * - PARTITION BY RANGE COLUMNS(column_list) (partition_definition [, ...])
 * - PARTITION BY LIST(expr) (partition_definition [, ...])
 * - PARTITION BY LIST COLUMNS(column_list) (partition_definition [, ...])
 */
class PartitionBy extends DdlParserAbstract
{
    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): PartitionByInterface
    {
        // Expect PARTITION BY
        if ($tokens->currentTokenAsString() !== 'PARTITION') {
            throw new ParseException('Expected PARTITION keyword');
        }

        if ($tokens->nextTokens()->currentTokenAsString() !== 'BY') {
            throw new ParseException('Expected BY keyword after PARTITION');
        }

        $tokens->nextTokens();

        $isLinear = false;
        if ($tokens->currentTokenAsString() === 'LINEAR') {
            $isLinear = true;
            $tokens->nextTokens();
        }

        $token = $tokens->currentTokenAsString();
        $expression = null;
        $columns = [];
        $algorithm = null;
        $isColumns = false;
        $partitionType = '';

        if (\in_array($token, ['HASH', 'RANGE', 'LIST'], true)) {
            $partitionType = $token;
            $tokens->nextTokens();

            if ($tokens->currentTokenAsString() === 'COLUMNS') {
                if (!\in_array($partitionType, ['RANGE', 'LIST'], true)) {
                    throw new ParseException('COLUMNS only allowed for RANGE or LIST');
                }

                $isColumns = true;
                $tokens->nextTokens();

                if ($tokens->currentTokenAsString() !== '(') {
                    throw new ParseException('Expected "(" after COLUMNS');
                }

                $tokens->nextTokens();
                $columns = $this->parseColumnList($tokens);

                if ($tokens->currentTokenAsString() !== ')') {
                    throw new ParseException('Expected ")" after column list');
                }

                $tokens->nextTokens();
            } else {
                $functionRef = new FunctionReference()->parseParameters($tokens, $partitionType);
                $params = $functionRef->getFunctionParameters();
                $expression = $params[0] ?? null;
            }
        } elseif ($token === 'KEY') {
            $partitionType = 'KEY';
            $tokens->nextTokens();

            if ($tokens->currentTokenAsString() === 'ALGORITHM') {
                $tokens->nextTokens();

                if ($tokens->currentTokenAsString() !== '=') {
                    throw new ParseException('Expected "=" after ALGORITHM');
                }

                [$type, $algorithmValue] = $tokens->nextToken();

                if ($type !== T_LNUMBER || !\in_array((int) $algorithmValue, [1, 2], true)) {
                    throw new ParseException('Expected ALGORITHM value 1 or 2');
                }

                $algorithm = (int) $algorithmValue;
                $tokens->nextTokens();
            }

            if ($tokens->currentTokenAsString() !== '(') {
                throw new ParseException('Expected "(" after KEY');
            }

            $tokens->nextTokens();
            $columns = $this->parseColumnList($tokens);

            if ($tokens->currentTokenAsString() !== ')') {
                throw new ParseException('Expected ")" after column list');
            }

            $tokens->nextTokens();
        } else {
            throw new ParseException("Expected HASH, KEY, RANGE, or LIST, got '{$token}'");
        }

        // Parse PARTITIONS count (optional, mainly for HASH/KEY)
        $partitionsCount = null;
        if ($tokens->currentTokenAsString() === 'PARTITIONS') {
            [$type, $token] = $tokens->nextToken();

            if ($type !== T_LNUMBER) {
                throw new ParseException('Expected number after PARTITIONS keyword');
            }

            $partitionsCount = (int) $token;
            $tokens->nextTokens();
        }

        // Parse SUBPARTITION BY (optional, not fully implemented yet)
        $subpartitionBy = null;
        if ($tokens->currentTokenAsString() === 'SUBPARTITION') {
            // Recursive parsing for subpartitions
            $subpartitionBy = $this->parseTokens($tokens);
        }

        // Parse partition definitions (for RANGE and LIST)
        $partitionDefinitions = [];
        if (\in_array($partitionType, ['RANGE', 'LIST'], true) && $tokens->currentTokenAsString() === '(') {
            $partitionDefinitions = $this->parsePartitionDefinitions($tokens);
        }

        return new PartitionByNode(
            $partitionType,
            $isLinear,
            $isColumns,
            $expression,
            $columns,
            $partitionsCount,
            $algorithm,
            $subpartitionBy,
            $partitionDefinitions
        );
    }

    /**
     * Parse column list: col1, col2, col3.
     *
     * @throws ParseException
     */
    protected function parseColumnList(TokensIteratorInterface $tokens): array
    {
        $columns = [];

        while ($tokens->valid() && $tokens->currentTokenAsString() !== ')') {
            $columnName = ColumnDefinition::parseColumnName($tokens);
            $columns[] = $columnName;

            if ($tokens->currentTokenAsString() === ')') {
                break;
            }

            if ($tokens->currentTokenAsString() !== ',') {
                throw new ParseException(
                    'Expected "," or ")" in column list, got "' . $tokens->currentTokenAsString() . '"'
                );
            }

            $tokens->nextTokens();
        }

        return $columns;
    }


    /**
     * Parse partition definitions: (PARTITION p0 VALUES LESS THAN (value), ...).
     *
     * @throws ParseException
     */
    protected function parsePartitionDefinitions(TokensIteratorInterface $tokens): array
    {
        if ($tokens->currentTokenAsString() !== '(') {
            return [];
        }

        $tokens->nextTokens();
        $definitions = [];

        while ($tokens->valid() && $tokens->currentTokenAsString() !== ')') {
            // Expect PARTITION keyword
            if ($tokens->currentTokenAsString() !== 'PARTITION') {
                throw new ParseException('Expected PARTITION keyword in partition definition');
            }

            $tokens->nextTokens();

            // Parse partition name
            $partitionName = ColumnDefinition::parseColumnName($tokens);

            // Parse VALUES clause (LESS THAN or IN)
            $valuesType = null;
            $values = [];

            if ($tokens->currentTokenAsString() === 'VALUES') {
                $tokens->nextTokens();

                $nextToken = $tokens->currentTokenAsString();

                if ($nextToken === 'LESS') {
                    if ($tokens->nextTokens()->currentTokenAsString() !== 'THAN') {
                        throw new ParseException('Expected THAN after LESS');
                    }

                    $valuesType = 'LESS THAN';
                    $tokens->nextTokens();
                } elseif ($nextToken === 'IN') {
                    $valuesType = 'IN';
                    $tokens->nextTokens();
                } else {
                    throw new ParseException('Expected LESS THAN or IN after VALUES');
                }

                // Parse value list: (value1, value2, ...)
                if ($tokens->currentTokenAsString() !== '(') {
                    throw new ParseException('Expected "(" after VALUES clause');
                }

                $tokens->nextTokens();
                $values = $this->parseValueList($tokens);

                if ($tokens->currentTokenAsString() !== ')') {
                    throw new ParseException('Expected ")" after VALUES list');
                }

                $tokens->nextTokens();
            }

            // Parse partition options (ENGINE, COMMENT, etc.) - simplified for now
            $options = [];
            while ($tokens->valid() &&
                   $tokens->currentTokenAsString() !== ',' &&
                   $tokens->currentTokenAsString() !== ')') {
                // Skip options for now (can be extended later)
                $tokens->nextTokens();
            }

            $definitions[] = new PartitionDefinition($partitionName, $valuesType, $values, $options);

            // Check for comma (more partitions) or closing parenthesis
            if ($tokens->currentTokenAsString() === ',') {
                $tokens->nextTokens();
            } elseif ($tokens->currentTokenAsString() === ')') {
                break;
            }
        }

        if ($tokens->currentTokenAsString() !== ')') {
            throw new ParseException('Expected ")" after partition definitions');
        }

        $tokens->nextTokens();

        return $definitions;
    }

    /**
     * Parse value list: value1, value2, ...
     *
     * @throws ParseException
     */
    protected function parseValueList(TokensIteratorInterface $tokens): array
    {
        $values = [];

        while ($tokens->valid() && $tokens->currentTokenAsString() !== ')') {
            [$type, $token] = $tokens->currentToken();

            if ($type === T_LNUMBER) {
                $values[] = (int) $token;
            } elseif ($type === T_DNUMBER) {
                $values[] = (float) $token;
            } elseif ($type === T_CONSTANT_ENCAPSED_STRING) {
                $values[] = \substr((string) $token, 1, -1);
            } elseif ($type === T_STRING) {
                // Function call or keyword like MAXVALUE
                $values[] = $token;
            } else {
                throw new ParseException('Unexpected token in value list: ' . $token);
            }

            $tokens->nextTokens();

            if ($tokens->currentTokenAsString() === ')') {
                break;
            }

            if ($tokens->currentTokenAsString() !== ',') {
                throw new ParseException(
                    'Expected "," or ")" in value list, got "' . $tokens->currentTokenAsString() . '"'
                );
            }

            $tokens->nextTokens();
        }

        return $values;
    }
}
