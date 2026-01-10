<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

use IfCastle\AQL\Dsl\Node\NodeHelper;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Constant\Variable;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FromUnixTime;
use IfCastle\AQL\Dsl\Sql\FunctionReference\UnixTimestamp;
use IfCastle\AQL\Executor\Context\PropertyContextInterface;

class PropertyTimestamp extends PropertyAbstract
{
    /**
     * Additional option for SELECT query
     * selects all timestamps as int.
     */
    public const OPT_SELECT_AS_INT         = 'select_timestamp_as_int';

    public function __construct(string $name, bool $isNullable = false)
    {
        parent::__construct($name, self::T_TIMESTAMP, $isNullable);
    }

    public function makeAsCreatedAt(): static
    {
        return $this;
    }

    public function makeAsUpdatedAt(): static
    {
        return $this;
    }

    #[\Override]
    protected function handleBefore(PropertyContextInterface $context, string $contextName): void
    {
        // Call parent handler
        parent::handleBefore($context, $contextName);

        if (NodeHelper::inTuple($context->getColumn()) && $context->getCurrentQuery()->isOption(self::OPT_SELECT_AS_INT)) {
            // Replace expression SELECT column FROM table to SELECT UNIX_TIMESTAMP(column) FROM table
            $context->substituteColumn(new UnixTimestamp(clone $context->getColumn()));
        }

        // Auto convert integer value to FromUnixtime(integer) expression
        $rightConstant              = $context->getRightConstant(false);

        if ($rightConstant instanceof ConstantInterface === false) {
            return;
        }

        $value                      = $rightConstant->getConstantValue();

        if (\is_int($value)) {
            // Replace expression column = integer to column = FROM_UNIXTIME(integer)
            $rightConstant->setSubstitution(new FromUnixTime(new Variable($value)));
        } elseif ($value instanceof \DateTimeInterface) {
            // Support DateTime class as value
            $rightConstant->setSubstitution(new Variable($value->format('Y-m-d H:i:s')));
        }
    }
}
