<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Constant\Variable;
use IfCastle\AQL\Dsl\Sql\Parameter\Parameter;
use IfCastle\AQL\Dsl\Sql\Parameter\ParameterInterface;
use IfCastle\Exceptions\UnexpectedValue;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;

/**
 * LIMIT whose row count and offset are constants or `{name}` parameters.
 *
 * The SQL has both numbers as plain integers, never as quoted strings, which MariaDB rejects. It is
 * `LIMIT offset, count`, or `LIMIT count` when the node is built without an offset: a MariaDB DELETE or UPDATE takes
 * no offset. A value that is not a non-negative integer raises UnexpectedValue, and so does rendering while a
 * parameter has no value: dropping the LIMIT would let a DELETE or UPDATE touch every row. A resolved count of
 * zero renders as `LIMIT 0`, not as no limit.
 */
final class LimitWithParameters extends NodeAbstract implements LimitInterface
{
    final public const string LIMIT = 'l';

    final public const string OFFSET = 'o';

    final public const string WITH_OFFSET = 'w';

    /**
     * Keys of a serialized count or offset: the parameter name (absent for a constant, null for an unnamed
     * parameter), the value (absent for a parameter without one) and the variable flag of a constant.
     */
    private const string SLOT_NAME = 'p';

    private const string SLOT_VALUE = 'v';

    private const string SLOT_VARIABLE = 'x';

    /**
     * @param bool $withOffset false renders `LIMIT count` and ignores $offset, which is then 0
     */
    public function __construct(
        ConstantInterface $limit,
        ConstantInterface $offset = new Variable(0),
        private readonly bool $withOffset = true
    ) {
        parent::__construct($offset, $limit);
    }

    /**
     * A parameter is restored by name and value only: its setter, reference and type are not carried.
     */
    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        return new self(
            self::slotFromArray($array[self::LIMIT] ?? []),
            self::slotFromArray($array[self::OFFSET] ?? []),
            $array[self::WITH_OFFSET] ?? true
        );
    }

    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        return [
            self::NODE_NAME         => $this->nodeName,
            self::LIMIT             => self::slotToArray($this->childNodes[1]),
            self::OFFSET            => self::slotToArray($this->childNodes[0]),
            self::WITH_OFFSET       => $this->withOffset,
        ];
    }

    /**
     * A parameterized limit is always rendered, so it is never empty.
     */
    #[\Override]
    public function isNotEmpty(): bool
    {
        return true;
    }

    /**
     * The offset, or 0 while its parameter has no value.
     *
     * @throws UnexpectedValue when the value is not a non-negative integer
     */
    #[\Override]
    public function getOffset(): int
    {
        return $this->valueOf($this->childNodes[0], 'OFFSET', false);
    }

    /**
     * The row count, or 0 while its parameter has no value.
     *
     * @throws UnexpectedValue when the value is not a non-negative integer
     */
    #[\Override]
    public function getLimit(): int
    {
        return $this->valueOf($this->childNodes[1], 'LIMIT', false);
    }

    #[\Override]
    public function setOffset(int $offset): static
    {
        $node                       = $this->childNodes[0];

        if ($node instanceof ParameterInterface) {
            $node->setParameterValue($offset);
        }

        return $this;
    }

    #[\Override]
    public function setLimit(int $limit): static
    {
        $node                       = $this->childNodes[1];

        if ($node instanceof ParameterInterface) {
            $node->setParameterValue($limit);
        }

        return $this;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $limit                      = $this->childNodes[1]->getAql($forResolved);

        return $this->withOffset ? 'LIMIT ' . $this->childNodes[0]->getAql($forResolved) . ', ' . $limit : 'LIMIT ' . $limit;
    }

    /**
     * @throws UnexpectedValue when a value is not a non-negative integer
     * @throws \IfCastle\Exceptions\UnexpectedMethodMode when a parameter has no value yet
     */
    #[\Override]
    protected function generateResult(): mixed
    {
        $limit                      = $this->valueOf($this->childNodes[1], 'LIMIT', true);

        if (!$this->withOffset) {
            return 'LIMIT ' . $limit;
        }

        return 'LIMIT ' . $this->valueOf($this->childNodes[0], 'OFFSET', true) . ', ' . $limit;
    }

    /**
     * @param bool $isRequired false reads a parameter without a value as 0, true fails on it
     */
    private function valueOf(ConstantInterface $node, string $clause, bool $isRequired): int
    {
        if (!$isRequired && $node instanceof ParameterInterface && false === $node->isParameterResolved()) {
            return 0;
        }

        $value                      = $node->getConstantValue();

        if (!\is_int($value) || $value < 0) {
            throw new UnexpectedValue($clause, $value, 'expected a non-negative integer');
        }

        return $value;
    }

    private static function slotToArray(ConstantInterface $node): array
    {
        if (!$node instanceof ParameterInterface) {
            return [self::SLOT_VALUE => $node->getConstantValue(), self::SLOT_VARIABLE => $node->isVariable()];
        }

        $name                       = $node->getParameterName();
        $slot                       = [self::SLOT_NAME => $name === '' ? null : $name];

        if ($node->isParameterResolved()) {
            $slot[self::SLOT_VALUE] = $node->getConstantValue();
        }

        return $slot;
    }

    private static function slotFromArray(array $slot): ConstantInterface
    {
        if (!\array_key_exists(self::SLOT_NAME, $slot)) {
            $value                  = $slot[self::SLOT_VALUE] ?? 0;

            return ($slot[self::SLOT_VARIABLE] ?? false) ? new Variable($value) : new Constant($value);
        }

        $parameter                  = new Parameter($slot[self::SLOT_NAME]);

        if (!\array_key_exists(self::SLOT_VALUE, $slot)) {
            return $parameter;
        }

        return $parameter->setParameterValue($slot[self::SLOT_VALUE]);
    }
}
