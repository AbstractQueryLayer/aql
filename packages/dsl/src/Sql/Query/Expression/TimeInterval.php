<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;

/**
 * Time interval expression
 * like: INTERVAL 1 DAY or INTERVAL {seconds} SECOND.
 *
 * The amount is a fixed integer or an expression node (parameter, constant, column, function call).
 * A constant amount always renders as a bare number, never as a quoted string.
 */
class TimeInterval extends NodeAbstract
{
    protected string $nodeName       = 'TIME_INTERVAL';

    public function __construct(protected TimeIntervalEnum $interval, protected int|NodeInterface $value)
    {
        parent::__construct();

        if ($value instanceof NodeInterface) {
            $this->childNodes[]     = $value->setParentNode($this);
        }
    }

    public function getInterval(): TimeIntervalEnum
    {
        return $this->interval;
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        $amount                     = $this->childNodes[0] ?? null;

        if ($amount === null) {
            return 'INTERVAL ' . $this->value . ' ' . $this->interval->value;
        }

        $result                     = $amount->getResult();

        if ($result === '') {
            return '';
        }

        if ($amount instanceof ConstantInterface && $amount->getPlaceholder() === null) {
            $result                 = (string) $this->numericValue($amount->getConstantValue());
        }

        return 'INTERVAL ' . $result . ' ' . $this->interval->value;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $amount                     = $this->childNodes[0] ?? null;

        return 'INTERVAL ' . ($amount?->getAql($forResolved) ?? $this->value) . ' ' . $this->interval->value;
    }

    /**
     * @throws TransformationException when the amount is not a number or a numeric string
     */
    private function numericValue(mixed $value): int|float
    {
        if (\is_int($value) || \is_float($value)) {
            return $value;
        }

        if (\is_string($value) && \is_numeric($value)) {
            return $value + 0;
        }

        throw new TransformationException([
            'template'          => 'The INTERVAL amount must be a number, got {type}',
            'type'              => \get_debug_type($value),
        ]);
    }
}
