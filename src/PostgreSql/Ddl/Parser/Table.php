<?php

declare(strict_types=1);

namespace IfCastle\AQL\PostgreSql\Ddl\Parser;

use IfCastle\AQL\Dsl\Ddl\Table as TableNode;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Parser\TokensIteratorInterface;
use IfCastle\AQL\MySql\Ddl\Parser\DdlParserAbstract;

class Table extends DdlParserAbstract
{
    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): TableNode
    {
        $tokens->assertTokenIs('CREATE');

        [$isTemporary, $isUnlogged, $scope] = $this->parseOptionalKeywords($tokens);

        // Check for TABLE keyword
        $tokens->assertTokenIs('TABLE');

        $isIfNotExists              = $tokens->assertTokens(false, 'IF', 'NOT', 'EXISTS');
        $tableName                  = $this->parseTableName($tokens);

        // Parse columns, constraints, LIKE, etc.
        [$columns, $constraints, $likeClause] = $this->parseCreateDefinitions($tokens);

        $inherits                   = $this->parseInherits($tokens);
        $partitionBy                = $this->parsePartitionBy($tokens);
        $usingMethod                = $this->parseUsingMethod($tokens);
        $storageParameters          = $this->parseStorageParameters($tokens);
        $onCommit                   = $this->parseOnCommit($tokens);
        $tablespace                 = $this->parseTablespace($tokens);

        $tokens->checkAndConsumeToken(';');

        return new TableNode(
            $tableName,
            $columns,
            $constraints,
            $likeClause,
            $inherits,
            $partitionBy,
            $usingMethod,
            $storageParameters,
            $onCommit,
            $tablespace,
            $isTemporary,
            $isUnlogged,
            $isIfNotExists,
            $scope
        );
    }

    private function parseOptionalKeywords(TokensIteratorInterface $tokens): array
    {
        $isTemporary                = false;
        $isUnlogged                 = false;
        $isIfNotExists              = false;
        $scope                      = null; // GLOBAL or LOCAL

        while (true) {

            $currentToken           = $tokens->currentTokenAsString();

            if ($currentToken === 'TABLE') {
                break;
            }

            if ($currentToken === 'TEMPORARY' || $currentToken === 'TEMP') {
                $isTemporary        = true;
            } elseif ($currentToken === 'UNLOGGED') {
                $isUnlogged         = true;
            } elseif ($currentToken === 'GLOBAL' || $currentToken === 'LOCAL') {
                $scope              = $currentToken;
            } else {
                continue;
            }

            $tokens->nextToken();
        }

        return [$isTemporary, $isUnlogged, $scope];
    }

    private function parseTableName(TokensIteratorInterface $tokens): string
    {
        $tableName                  = $tokens->currentTokenAsString();
        $tokens->nextToken();
        return $tableName;
    }

    private function parseCreateDefinitions(TokensIteratorInterface $tokens): array
    {
        $columns                    = [];
        $constraints                = [];
        $likeClause                 = null;

        if ($tokens->currentTokenAsString() === '(') {
            $tokens->nextToken();

            while ($tokens->currentTokenAsString() !== ')') {
                if ($tokens->currentTokenAsString() === 'LIKE') {
                    $likeClause = $this->parseLikeClause();
                } elseif ($this->isColumnDefinition()) {
                    $columns[] = $this->parseColumnDefinition($tokens);
                } else {
                    $constraints[] = $this->parseTableConstraint();
                }

                if ($tokens->currentTokenAsString() === ',') {
                    $tokens->nextToken();
                }
            }

            $tokens->nextToken(); // Move past the closing ')'
        }

        return [$columns, $constraints, $likeClause];
    }

    private function isColumnDefinition(): bool
    {
        // Logic to determine if the current token sequence represents a column definition
    }

    private function parseColumnDefinition(TokensIteratorInterface $tokens): array
    {
        $columnName                 = $tokens->currentTokenAsString();
        $tokens->nextToken();
        $dataType                   = $this->parseDataType($tokens);

        $columnOptions              = [];

        while (!$this->isEndOfColumnDefinition($tokens)) {
            $columnOptions[] = $this->parseColumnOption($tokens);
        }

        return ['name' => $columnName, 'type' => $dataType, 'options' => $columnOptions];
    }

    private function isEndOfColumnDefinition(TokensIteratorInterface $tokens): bool
    {
        $currentToken               = $tokens->currentTokenAsString();

        return $currentToken === ',' || $currentToken === ')';
    }

    private function parseDataType(TokensIteratorInterface $tokens): string
    {
        $dataType                   = $tokens->currentTokenAsString();
        $tokens->nextToken();
        return $dataType;
    }

    private function parseColumnOption(TokensIteratorInterface $tokens): array
    {
        $option                     = $tokens->currentTokenAsString();
        $tokens->nextToken();

        switch ($option) {
            case 'STORAGE':
                $storage = $tokens->currentTokenAsString();
                $tokens->nextToken();
                return ['storage' => $storage];

            case 'COMPRESSION':
                $compression = $tokens->currentTokenAsString();
                $tokens->nextToken();
                return ['compression' => $compression];

            case 'COLLATE':
                $collation = $tokens->currentTokenAsString();
                $tokens->nextToken();
                return ['collation' => $collation];

            case 'CONSTRAINT':
            case 'NOT':
            case 'NULL':
            case 'CHECK':
            case 'DEFAULT':
            case 'GENERATED':
            case 'UNIQUE':
            case 'PRIMARY':
            case 'REFERENCES':
                return $this->parseColumnConstraint();

            default:
                throw new ParseException('Unknown column option: ' . $option);
        }
    }

    private function parseColumnConstraint(): array
    {
        // Logic to parse column constraints like NOT NULL, CHECK, etc.
    }

    private function parseTableConstraint(): array
    {
        // Logic to parse table-level constraints
    }

    private function parseLikeClause(): array
    {
        // Logic to parse the LIKE clause
    }

    private function parseInherits(TokensIteratorInterface $tokens): ?array
    {
        if ($tokens->currentTokenAsString() === 'INHERITS') {
            $tokens->nextToken(); // Skip INHERITS
            $tokens->assertTokenIs('(');
            $inherits = [];

            while ($tokens->currentTokenAsString() !== ')') {
                $inherits[] = $tokens->currentTokenAsString();
                $tokens->nextToken();

                if ($tokens->currentTokenAsString() === ',') {
                    $tokens->nextToken();
                }
            }

            $tokens->nextToken(); // Skip ')'
            return $inherits;
        }

        return null;
    }

    private function parsePartitionBy(TokensIteratorInterface $tokens): ?array
    {
        if ($tokens->currentTokenAsString() === 'PARTITION') {
            $tokens->nextToken(); // Skip PARTITION
            $tokens->assertTokenIs('BY');
            $tokens->nextToken(); // Skip BY

            $partitionType = $tokens->currentTokenAsString();
            $tokens->nextToken(); // Skip partition type (RANGE, LIST, HASH)

            $columns = [];
            $tokens->assertTokenIs('(');
            $tokens->nextToken(); // Skip '('

            while ($tokens->currentTokenAsString() !== ')') {
                $columns[] = $tokens->currentTokenAsString();
                $tokens->nextToken();

                if ($tokens->currentTokenAsString() === ',') {
                    $tokens->nextToken();
                }
            }

            $tokens->nextToken(); // Skip ')'

            return ['type' => $partitionType, 'columns' => $columns];
        }

        return null;
    }

    private function parseUsingMethod(TokensIteratorInterface $tokens): ?string
    {
        if ($tokens->currentTokenAsString() === 'USING') {
            $tokens->nextToken(); // Skip USING
            $method = $tokens->currentTokenAsString();
            $tokens->nextToken(); // Move past method name
            return $method;
        }

        return null;
    }

    private function parseStorageParameters(TokensIteratorInterface $tokens): ?array
    {
        if ($tokens->currentTokenAsString() === 'WITH') {
            $tokens->nextToken(); // Skip WITH
            $tokens->assertTokenIs('(');
            $tokens->nextToken(); // Skip '('

            $parameters = [];

            while ($tokens->currentTokenAsString() !== ')') {
                $parameter = $tokens->currentTokenAsString();
                $tokens->nextToken();

                if ($tokens->currentTokenAsString() === '=') {
                    $tokens->nextToken();
                    $value = $tokens->currentTokenAsString();
                    $parameters[$parameter] = $value;
                    $tokens->nextToken();
                } else {
                    $parameters[$parameter] = true;
                }

                if ($tokens->currentTokenAsString() === ',') {
                    $tokens->nextToken();
                }
            }

            $tokens->nextToken(); // Skip ')'
            return $parameters;
        }

        if ($tokens->currentTokenAsString() === 'WITHOUT') {
            $tokens->nextToken(); // Skip WITHOUT
            $tokens->assertTokenIs('OIDS');
            $tokens->nextToken(); // Skip OIDS
            return ['oids' => false];
        }

        return null;
    }

    private function parseOnCommit(TokensIteratorInterface $tokens): ?string
    {
        if ($tokens->currentTokenAsString() === 'ON') {
            $tokens->nextToken(); // Skip ON
            $tokens->assertTokenIs('COMMIT');
            $tokens->nextToken(); // Skip COMMIT

            $commitAction = $tokens->currentTokenAsString();
            $tokens->nextToken(); // Move past action (PRESERVE ROWS, DELETE ROWS, DROP)
            return $commitAction;
        }

        return null;
    }

    private function parseTablespace(TokensIteratorInterface $tokens): ?string
    {
        if ($tokens->currentTokenAsString() === 'TABLESPACE') {
            $tokens->nextToken(); // Skip TABLESPACE
            $tablespaceName = $tokens->currentTokenAsString();
            $tokens->nextToken(); // Move past tablespace name
            return $tablespaceName;
        }

        return null;
    }
}
