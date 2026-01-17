<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\DuplicateKeyInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Subject;
use IfCastle\AQL\Dsl\Sql\Query\Expression\ValueList as ValueListNode;
use IfCastle\AQL\Dsl\Sql\Query\Insert as InsertNode;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\Exceptions\UnexpectedValue;

class Insert extends AqlParserAbstract
{
    /**
     * @throws ParseException
     * @throws UnexpectedValue
     */
    #[\Override]
    public function parseTokens(TokensIteratorInterface $tokens): InsertNode
    {
        $action                     = $tokens->currentTokenAsString();

        // 1. Expected keyword ACTION
        if (false === \in_array($action, [QueryInterface::ACTION_INSERT, QueryInterface::ACTION_REPLACE], true)) {
            throw new ParseException('Expected keyword ' . QueryInterface::ACTION_INSERT . ' or ' . QueryInterface::ACTION_REPLACE);
        }

        $tokens->increaseRecursionDepth();

        [$type, $token, $line]      = $tokens->nextToken();

        // Support token INTO
        if ($tokens->currentTokenAsString() === 'INTO') {
            [$type, $token, $line]  = $tokens->nextToken();
        }

        // 2. Expected Entity name
        if ($type !== T_STRING) {
            throw new ParseException('Expected Entity name ' . \sprintf('(got \'%s\')', $token), ['line' => $line]);
        }

        $subject                    = new Subject(\ucfirst((string) $token));
        $assigmentList              = null;

        // 3. Expect SET keyword or ()
        $tokens->nextToken();

        if ($tokens->currentTokenAsString() === 'SET') {
            $assigmentList          = (new AssignmentList())->parseTokens($tokens);
        }

        // 4. Columns definition (optional)
        // (column1, column2, column3, ...)
        $columns                    = $this->tryToParseColumns($tokens);
        $select                     = null;
        $values                     = null;

        // 5. INSERT ... SELECT Statement ?
        if ($tokens->currentTokenAsString() === QueryInterface::ACTION_SELECT) {
            $select                 = (new Subquery())->parseTokens($tokens);
        } elseif ($tokens->currentTokenAsString() === 'VALUES') {
            // EXPECTED VALUES keyword
            $values                 = (new ValueList())->parseValues($tokens, $columns);
        }

        //
        // Case for INSERT ... SELECT Statement
        //
        if ($values === null && $columns !== null) {
            $values                 = new ValueListNode(...$columns);
        }

        // 6. ON DUPLICATE KEY UPDATE
        $onDuplicateKey             = $this->parseOnDuplicateKeyUpdate($tokens);

        $insertNode                 = new InsertNode($subject);

        if ($action === QueryInterface::ACTION_REPLACE) {
            $insertNode->markAsReplace();
        }

        if ($assigmentList !== null) {
            $insertNode->setAssigmentList($assigmentList);
        }

        if ($values !== null) {
            $insertNode->setValueList($values);
        }

        if ($select !== null) {
            $insertNode->setFromSelect($select);
        }

        if ($onDuplicateKey !== null) {
            $insertNode->setDuplicateKey($onDuplicateKey);
        }

        $tokens->decreaseRecursionDepth();

        return $insertNode;
    }

    /**
     * @throws ParseException
     * @throws UnexpectedValue
     */
    public function parseOnDuplicateKeyUpdate(TokensIteratorInterface $tokens): ?DuplicateKeyInterface
    {
        if ($tokens->currentTokenAsString() !== 'ON') {
            return null;
        }

        $tokens->expectKeywords('ON DUPLICATE KEY UPDATE');

        $result                     = (new DuplicateKey())->parseAssignments($tokens);

        if ($result instanceof DuplicateKeyInterface) {
            return $result;
        }

        throw new UnexpectedValue('$result', $result, DuplicateKeyInterface::class);
    }
}
