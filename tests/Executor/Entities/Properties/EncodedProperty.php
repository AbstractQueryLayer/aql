<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Entities\Properties;

use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Entity\Property\PropertyAbstract;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\Context\PropertyContextInterface;
use IfCastle\AQL\Executor\Helpers\ContextHelper;
use IfCastle\AQL\Executor\Plan\RowModifier;

/**
 * An example of an encoded property that is used to encode and decode a property value.
 */
final class EncodedProperty extends PropertyAbstract
{
    final public const string ENCODED_PROPERTY = 'encodedProperty';

    public function __construct(string $name = self::ENCODED_PROPERTY)
    {
        parent::__construct($name);
    }

    protected function handleBefore(PropertyContextInterface $context, string $contextName): void
    {
        parent::handleBefore($context, $contextName);

        switch (ContextHelper::resolveContextName($context->getCurrentNode())) {
            case NodeContextInterface::CONTEXT_TUPLE:
                $alias              = $context->getTupleColumn()?->getAliasOrColumnName();

                if ($alias === null) {
                    break;
                }

                $context->getResultProcessingPlan()->addRowModifier(new RowModifier(fn($rows) => $rows[$alias] = \base64_decode($rows[$alias] ?? '')));
                break;

            case NodeContextInterface::CONTEXT_FILTER:
            case NodeContextInterface::CONTEXT_ASSIGN:
                $context->getRightConstant()->setSubstitution(new Constant(\base64_encode($context->getRightConstant()->getConstantValue())));
                break;
        }
    }
}
