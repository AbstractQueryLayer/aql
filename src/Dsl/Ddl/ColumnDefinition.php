<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;

class ColumnDefinition extends DdlStatementAbstract implements ColumnDefinitionInterface
{
    protected string $autoIncrement  = 'AUTO_INCREMENT';

    public function __construct(
        protected string $columnName,
        protected string $columnType,
        protected ?int $maximumDisplayWidth   = null,
        protected ?int $digitsNumber          = null,
        protected ?bool $isUnsigned           = null,
        protected bool $isNull                = true,
        protected bool $isZerofill            = false,
        protected bool $isAutoIncrement       = false,
        protected mixed $defaultValue         = null,
        protected ?array $variants            = null,
        protected ?string $comment            = null,
        protected string|int|null $afterColumn = null

    ) {
        parent::__construct();
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        return new self(
            $array[self::COLUMN_NAME] ?? '',
            $array[self::COLUMN_TYPE] ?? '',
            $array[self::MAXIMUM_DISPLAY_WIDTH] ?? null,
            $array[self::DIGITS_NUMBER] ?? null,
            $array[self::UNSIGNED] ?? null,
            $array[self::NULL] ?? true,
            $array[self::ZEROFILL] ?? false,
            $array[self::AUTO_INCREMENT] ?? false,
            $array[self::DEFAULT_VALUE] ?? null,
            $array[self::VARIANTS] ?? null,
            $array[self::COMMENT] ?? null,
            $array[self::AFTER_COLUMN] ?? null
        );
    }

    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        return [
            self::COLUMN_NAME              => $this->columnName,
            self::COLUMN_TYPE              => $this->columnType,
            self::MAXIMUM_DISPLAY_WIDTH    => $this->maximumDisplayWidth,
            self::DIGITS_NUMBER            => $this->digitsNumber,
            self::UNSIGNED                 => $this->isUnsigned,
            self::NULL                     => $this->isNull,
            self::ZEROFILL                 => $this->isZerofill,
            self::AUTO_INCREMENT           => $this->isAutoIncrement,
            self::DEFAULT_VALUE            => $this->defaultValue,
            self::VARIANTS                 => $this->variants,
            self::COMMENT                  => $this->comment,
            self::AFTER_COLUMN             => $this->afterColumn,
        ];
    }

    public function describeDefaultValue(mixed $value): static
    {
        $this->defaultValue         = $value;

        return $this;
    }

    public function describeComment(string $comment): static
    {
        $this->comment              = $comment;

        return $this;
    }

    #[\Override]
    public function getColumnName(): string
    {
        return $this->columnName;
    }

    #[\Override]
    public function getColumnType(): string
    {
        return $this->columnType;
    }

    #[\Override]
    public function getMaximumDisplayWidth(): ?int
    {
        return $this->maximumDisplayWidth;
    }

    #[\Override]
    public function getDigitsNumber(): ?int
    {
        return $this->digitsNumber;
    }

    #[\Override]
    public function isUnsigned(): ?bool
    {
        return $this->isUnsigned;
    }

    #[\Override]
    public function resetUnsigned(): static
    {
        $this->isUnsigned           = null;
        return $this;
    }

    #[\Override]
    public function isNull(): bool
    {
        return $this->isNull;
    }

    #[\Override]
    public function isZerofill(): bool
    {
        return $this->isZerofill;
    }

    #[\Override]
    public function isAutoIncrement(): bool
    {
        return $this->isAutoIncrement;
    }

    /**
     * @return mixed|null
     */
    #[\Override]
    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    /**
     * @param mixed|null $defaultValue
     *
     * @return $this
     */
    public function setDefaultValue(mixed $defaultValue): static
    {
        $this->defaultValue         = $defaultValue;
        return $this;
    }

    #[\Override]
    public function getVariants(): ?array
    {
        return $this->variants;
    }


    public function setVariants(array $variants): static
    {
        $this->variants             = $variants;
        return $this;
    }

    #[\Override]
    public function resetVariants(): static
    {
        $this->variants             = null;
        return $this;
    }

    #[\Override]
    public function getComment(): ?string
    {
        return $this->comment;
    }

    #[\Override]
    public function getAfterColumn(): int|string|null
    {
        return $this->afterColumn;
    }

    #[\Override]
    public function defineAutoIncrement(string $autoIncrement): static
    {
        $this->autoIncrement        = $autoIncrement;

        return $this;
    }

    public function setAfterColumn(int|string $afterColumn): static
    {
        $this->afterColumn          = $afterColumn;

        return $this;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->generateResult();
    }

    #[\Override]
    protected function generateResult(): string
    {
        $sql                        = [];

        if ($this->columnName === '') {
            return '';
        }

        $sql[]                      = $this->escape($this->columnName);
        $sql[]                      = $this->columnType;

        if ($this->maximumDisplayWidth !== null && $this->digitsNumber !== null) {
            $sql[]                  = \sprintf('(%d, %d)', $this->maximumDisplayWidth, $this->digitsNumber);
        } elseif ($this->maximumDisplayWidth !== null) {
            $sql[]                  = \sprintf('(%d)', $this->maximumDisplayWidth);
        }

        if (\is_array($this->variants)) {
            $sql[]                  = $this->generateVariants();
        }

        if ($this->isUnsigned) {
            $sql[]                  = 'UNSIGNED';
        }

        if ($this->isZerofill) {
            $sql[]                  = 'ZEROFILL';
        }

        $sql[]                      = $this->isNull ? 'NULL' : 'NOT NULL';

        if ($this->defaultValue !== null) {

            if ($this->defaultValue instanceof NodeInterface) {
                $value              = $this->defaultValue->getResult();
            } elseif (\is_string($this->defaultValue)) {
                $value              = $this->quote($this->defaultValue);
            } else {
                $value              = $this->defaultValue;
            }

            $sql[]                  = 'DEFAULT ' . $value;
        }

        if ($this->isAutoIncrement) {
            $sql[]                  = $this->generateAutoIncrement();
        }

        if ($this->comment !== null && $this->comment !== '' && $this->comment !== '0') {
            $sql[]                  = 'COMMENT ' . $this->quote($this->comment);
        }

        // Support expression for ALTER TABLE
        if ($this->afterColumn === 0) {
            $sql[]                  = 'FIRST';
        } elseif (\is_string($this->afterColumn)) {
            $sql[]                  = 'AFTER ' . $this->escape($this->afterColumn);
        }

        return \implode(' ', $sql);
    }

    protected function generateAutoIncrement(): string
    {
        return $this->autoIncrement;
    }

    protected function generateVariants(): string
    {
        $variants               = [];

        foreach ($this->variants as $variant) {
            if (\is_int($variant) || \is_float($variant)) {
                $variants[]     = $variant;
            } elseif (\is_string($variant)) {
                $variants[]     = $this->quote($variant);
            }
        }

        return '(' . \implode(',', $variants) . ')';
    }
}
