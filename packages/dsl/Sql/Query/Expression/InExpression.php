<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
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
}
