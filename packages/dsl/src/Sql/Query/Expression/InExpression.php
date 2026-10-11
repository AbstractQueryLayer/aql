<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;

/**
 * Expressions:
 * [NOT] IN (constant1, ...)
 * or
 * [NOT] IN (SELECT * FROM...)
 * or
 * [NOT] IN THE EntityName
 *
 * Right side expression which allows you to describe relationships with another entity
 * either through a subquery or through a THE-expression.
 *
 */
class InExpression extends NodeAbstract
{
    public const string IN          = 'IN';

    public const string THE         = 'THE';

    protected bool $isSubquery      = false;

    public function __construct(array|SubqueryInterface $values)
    {
        parent::__construct();

        if (\is_array($values)) {
            $this->childNodes       = $values;
        } else {
            $this->isSubquery       = true;
            $this->childNodes[self::IN] = $values;
        }

        foreach ($this->childNodes as $node) {
            $node->setParentNode($this);
        }
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        if ($this->isSubquery) {
            return $this->childNodes[self::IN]->getAql($forResolved);
        }

        $results                    = $this->childNodesToAql(', ');

        if ($results === '') {
            return '';
        }

        return '(' . $results . ')';
    }

    /**
     * A subquery renders its own parentheses; a value list gets them here, with its values separated by commas.
     */
    #[\Override]
    protected function generateResult(): mixed
    {
        $results                    = $this->generateResultForChildNodes();

        if ($this->isSubquery || $results === []) {
            return \implode(' ', $results);
        }

        // IN ({ids}) with a list applied to {ids}: the list renders its own parentheses.
        $only                       = \count($this->childNodes) === 1 ? \reset($this->childNodes) : null;

        if ($only instanceof ConstantInterface && $only->isValueList()) {
            return $results[\array_key_first($results)];
        }

        return '(' . \implode(', ', $results) . ')';
    }
}
