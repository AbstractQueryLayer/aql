<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface ColumnDefinitionInterface extends NodeInterface
{
    public const string COLUMN_NAME         = 'columnName';

    public const string COLUMN_TYPE         = 'columnType';

    public const string MAXIMUM_DISPLAY_WIDTH = 'maximumDisplayWidth';

    public const string DIGITS_NUMBER       = 'digitsNumber';

    public const string UNSIGNED            = 'isUnsigned';

    public const string NULL                = 'isNull';

    public const string ZEROFILL            = 'isZerofill';

    public const string AUTO_INCREMENT      = 'isAutoIncrement';

    public const string DEFAULT_VALUE       = 'defaultValue';

    public const string VARIANTS            = 'variants';

    public const string COMMENT             = 'comment';

    public const string AFTER_COLUMN        = 'afterColumn';

    public function getColumnName(): string;

    public function getColumnType(): string;

    public function getMaximumDisplayWidth(): ?int;

    public function getDigitsNumber(): ?int;

    public function isUnsigned(): ?bool;

    public function resetUnsigned(): static;

    public function isNull(): bool;

    public function isZerofill(): bool;

    public function isAutoIncrement(): bool;

    public function getDefaultValue(): mixed;

    public function getVariants(): ?array;

    public function resetVariants(): static;

    public function getComment(): ?string;

    public function getAfterColumn(): int|string|null;

    public function defineAutoIncrement(string $autoIncrement): static;
}
