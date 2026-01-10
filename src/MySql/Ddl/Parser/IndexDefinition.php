<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Ddl\Parser;

use IfCastle\AQL\Dsl\Ddl\IndexDefinition as IndexDefinitionNode;
use IfCastle\AQL\Dsl\Ddl\IndexDefinitionInterface;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Parser\TokensIteratorInterface;

class IndexDefinition extends DdlParserAbstract
{
    public static function isIndexStart(TokensIteratorInterface $tokens): bool
    {
        return match ($tokens->currentTokenAsString()) {
            'INDEX', 'KEY', 'PRIMARY', 'UNIQUE', 'FULLTEXT', 'SPATIAL' => true,
            default => false,
        };
    }

    /**
     * @throws ParseException
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): IndexDefinitionInterface
    {
        $token                      = $tokens->currentTokenAsString();
        $indexRole                  = null;

        if (\in_array($token, ['PRIMARY', 'UNIQUE', 'FULLTEXT', 'SPATIAL'], true)) {
            $indexRole              = $token;
            $tokens->nextTokens();
            $token                  = $tokens->currentTokenAsString();
        }

        if ($token !== 'INDEX' && $token !== 'KEY') {
            throw new ParseException('Expected INDEX or KEY ' . \sprintf('(got \'%s\')', $token));
        }

        $tokens->nextTokens();

        $indexName                  = ColumnDefinition::parseColumnName($tokens, false);
        $indexType                  = ColumnDefinition::parseColumnName($tokens, false);

        // parse expression (col_name, ...)
        $indexParts                 = ColumnDefinition::parseColumnsList($tokens);

        // skip unsupported options
        while ($tokens->valid() && !\in_array($tokens->currentTokenAsString(), [',', ')'])) {
            $tokens->nextTokens();
        }

        return new IndexDefinitionNode($indexName, $indexType, $indexRole, $indexParts);
    }
}
