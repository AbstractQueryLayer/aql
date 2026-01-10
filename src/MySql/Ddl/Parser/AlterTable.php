<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Ddl\Parser;

use IfCastle\AQL\Dsl\Ddl\AlterTable as AlterTableNode;
use IfCastle\AQL\Dsl\Ddl\AlterTableInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Parser\TokensIteratorInterface;

class AlterTable extends DdlParserAbstract
{
    final public const string ALTER        = 'ALTER';

    public static function isAlterTableStart(TokensIteratorInterface $tokens): bool
    {
        return $tokens->currentTokenAsString() !== self::ALTER;
    }

    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): AlterTableInterface
    {
        // see https://dev.mysql.com/doc/refman/8.0/en/alter-table.html
        if ($tokens->currentTokenAsString() !== self::ALTER) {
            throw new ParseException('Expected keyword ' . self::ALTER);
        }

        if ($tokens->nextTokens()->currentTokenAsString() !== 'TABLE') {
            throw new ParseException('Expected keyword TABLE');
        }

        $tableName                  = ColumnDefinition::parseColumnName($tokens->nextTokens());

        $alterOptions               = [];
        $oldStopTokens              = $tokens->getStopTokens();
        $stopTokens                 = $oldStopTokens;
        // default stop token
        $stopTokens[';']            = true;
        $tokens->setStopTokens($stopTokens);

        while ($tokens->valid()) {
            // Check if this is PARTITION BY (not an alter option)
            if ($tokens->currentTokenAsString() === 'PARTITION') {
                break;
            }

            $alterOptions[]         = (new AlterOption())->parseTokens($tokens);

            if (\array_key_exists($tokens->currentTokenAsString(), $stopTokens)) {
                break;
            }

            if ($tokens->currentTokenAsString() !== ',') {
                break;
            }

            $tokens->nextTokens();
        }

        $partitionOptions           = null;

        // Parse PARTITION BY
        if ($tokens->currentTokenAsString() === 'PARTITION') {
            $partitionOptions       = (new PartitionBy())->parseTokens($tokens);
        }

        $tokens->setStopTokens($oldStopTokens);

        return new AlterTableNode($tableName, $alterOptions, $partitionOptions);
    }
}
