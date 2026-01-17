<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Ddl\Parser;

use IfCastle\AQL\Dsl\Ddl\RenameDefinition;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Parser\TokensIteratorInterface;

class Rename extends DdlParserAbstract
{
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): RenameDefinition
    {
        // Expression
        // old_col_name TO new_col_name
        $oldName                    = ColumnDefinition::parseColumnName($tokens);

        if ($tokens->currentTokenAsString() !== 'TO') {
            throw new ParseException(
                'Expected keyword "TO" for RENAME column ' . \sprintf('(got \'%s\')', $tokens->currentTokenAsString())
            );
        }

        $newName                    = ColumnDefinition::parseColumnName($tokens->nextTokens());

        return new RenameDefinition($oldName, $newName);
    }
}
