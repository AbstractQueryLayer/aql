<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\Exceptions\UnexpectedValue;

/**
 * Value expression under an explicit collation: `title COLLATE utf8mb4_nopad_bin`.
 *
 * The collation decides how the value compares: a binary NO PAD collation tells 'Moon' from 'moon', 'Moön' and
 * 'Moon '. The name is rendered as written, so it is limited to letters, digits and underscores.
 */
class Collate extends NodeAbstract
{
    protected string $nodeName      = 'COLLATE';

    /**
     * @param string $collation     collation name of the database, such as utf8mb4_nopad_bin
     * @throws UnexpectedValue      when the name holds a character outside [A-Za-z0-9_]
     */
    public function __construct(NodeInterface $expression, protected string $collation)
    {
        if (\preg_match('/^[A-Za-z0-9_]+$/', $collation) !== 1) {
            throw new UnexpectedValue('$collation', $collation, 'letters, digits and underscores');
        }

        parent::__construct($expression);
    }

    public function getCollation(): string
    {
        return $this->collation;
    }

    public function getExpression(): NodeInterface
    {
        return $this->childNodes[0];
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->childNodes[0]->getAql($forResolved) . ' COLLATE ' . $this->collation;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $expression                 = $this->childNodes[0]->getResult();

        if ($expression === '') {
            return '';
        }

        return $expression . ' COLLATE ' . $this->collation;
    }
}
