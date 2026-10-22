<?php

declare(strict_types=1);

namespace IfCastle\AQL\PdoDriver;

use IfCastle\AQL\Dsl\Sql\Constant\BinaryString;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Storage\SqlStatementInterface;
use IfCastle\Exceptions\UnexpectedValue;
use IfCastle\Exceptions\UnexpectedValueType;

/**
 * A PDO prepared statement and the constants bound to its placeholders.
 *
 * A bound constant is read when the statement executes, not when it is bound: a parameter given a new value
 * runs the same statement with that value.
 */
final class PDOStatementAdapter implements SqlStatementInterface
{
    /**
     * @var array<string, ConstantInterface> by placeholder
     */
    private array $parameters       = [];

    public function __construct(private readonly \PDOStatement $statement) {}

    #[\Override]
    public function getQuery(): string
    {
        return $this->statement->queryString;
    }

    public function getPdoStatement(): \PDOStatement
    {
        return $this->statement;
    }

    /**
     * @return array<string, ConstantInterface> the bound constants by placeholder
     */
    #[\Override]
    public function getParameterDefinitions(): array
    {
        return $this->parameters;
    }

    /**
     * @return list<string> the bound placeholders
     */
    #[\Override]
    public function getParameterKeys(): array
    {
        return \array_keys($this->parameters);
    }

    /**
     * Binds the constant to its placeholder; a constant bound later to the same placeholder replaces it.
     *
     * @throws UnexpectedValue when the constant has no named placeholder
     */
    #[\Override]
    public function bindParameter(ConstantInterface $parameter): void
    {
        $placeholder                = $parameter->getPlaceholder();

        if ($placeholder === null || $placeholder === '?') {
            throw new UnexpectedValue('$parameter', $placeholder, 'a named placeholder');
        }

        $this->parameters[$placeholder] = $parameter;
    }

    /**
     * Passes the values of the bound constants and then the given values to PDO, each with the PDO type of its
     * value; a given value replaces a bound constant of the same placeholder. A placeholder the statement does not
     * have fails its execution.
     *
     * @param array<string, mixed> $values values by placeholder
     *
     * @throws UnexpectedValueType when a value has no SQL form: an array, or an object other than a date,
     *                             a Stringable or a BinaryString
     */
    public function bindValues(array $values): void
    {
        foreach ($this->parameters as $placeholder => $parameter) {
            if (\array_key_exists($placeholder, $values)) {
                continue;
            }

            $isBinary               = $parameter instanceof Constant && $parameter->isBinary();
            $this->bindValue($placeholder, $parameter->getConstantValue(), $isBinary);
        }

        foreach ($values as $placeholder => $value) {
            $this->bindValue($placeholder, $value, false);
        }
    }

    private function bindValue(string $placeholder, mixed $value, bool $isBinary): void
    {
        // With emulated prepares pdo_mysql writes a LOB as _binary'…', so its bytes are not checked against the
        // connection charset.
        [$value, $type]             = match (true) {
            $value === null         => [null, \PDO::PARAM_NULL],
            \is_bool($value)        => [$value, \PDO::PARAM_BOOL],
            \is_int($value)         => [$value, \PDO::PARAM_INT],
            $value instanceof BinaryString => [$value->bytes, \PDO::PARAM_LOB],
            \is_string($value)      => [$value, $isBinary ? \PDO::PARAM_LOB : \PDO::PARAM_STR],
            \is_float($value), $value instanceof \Stringable => [(string) $value, \PDO::PARAM_STR],
            $value instanceof \DateTimeInterface => [$value->format('Y-m-d H:i:s'), \PDO::PARAM_STR],
            default                 => throw new UnexpectedValueType(
                $placeholder,
                $value,
                'a scalar, null, a date, a Stringable or a BinaryString'
            ),
        };

        $this->statement->bindValue($placeholder, $value, $type);
    }
}
