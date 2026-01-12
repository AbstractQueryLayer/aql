<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;

class ChangeColumn extends DdlStatementAbstract implements ChangeColumnInterface
{
    public function __construct(protected string $oldColumnName, ColumnDefinitionInterface $columnDefinition)
    {
        parent::__construct();

        $this->childNodes[self::DEFINITION] = $columnDefinition;
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        return new self(
            $array[self::OLD_NAME] ?? '',
            ColumnDefinition::fromArray(ArrayTyped::unserialize($array[self::DEFINITION] ?? null), $validator)
        );
    }

    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        return [
            self::OLD_NAME          => $this->oldColumnName,
            self::DEFINITION        => ArrayTyped::serialize($this->getColumnDefinition(), $validator),
        ];
    }

    #[\Override]
    public function getOldColumnName(): string
    {
        return $this->oldColumnName;
    }

    /**
     * @return ColumnDefinition
     */
    #[\Override]
    public function getColumnDefinition(): ColumnDefinitionInterface
    {
        return $this->childNodes[self::DEFINITION];
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        return $this->generateResult();
    }

    #[\Override]
    protected function generateResult(): string
    {
        return $this->escape($this->oldColumnName) . ' ' . $this->getColumnDefinition()->getResult();
    }
}
