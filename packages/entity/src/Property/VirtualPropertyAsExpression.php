<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Executor\Context\PropertyContextInterface;

/**
 * ## VirtualPropertyAsExpression.
 *
 * Replaces an occurrence of a property with an expression.
 * Only for results, filters, order, group and relations,
 * Not for assign expressions.
 *
 * Example:
 *
 * SELECT full_name FROM students => SELECT CONCAT_WS(first_name, last_name) as full_name FROM students
 *
 */
class VirtualPropertyAsExpression extends VirtualProperty
{
    public function __construct(string $name, protected NodeInterface $expression, string $type = self::T_STRING)
    {
        parent::__construct($name, $type);
    }

    #[\Override]
    protected function applyVirtualHandler(PropertyContextInterface $context, string $contextName): bool
    {
        $context->getColumn()->setSubstitution(clone $this->expression);
        return true;
    }
}
