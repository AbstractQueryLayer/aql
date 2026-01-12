<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Sql\Query\Expression\NodeList;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;

class AlterTable extends DdlStatementAbstract implements AlterTableInterface
{
    public function __construct(
        protected string $tableName,
        array $alterOptions = [],
        protected ?PartitionByInterface $partitionOptions = null
    ) {
        parent::__construct();
        $this->childNodes[self::ALTER_OPTIONS] = (new NodeList(...$alterOptions))->defineDelimiter("\n");

        if ($partitionOptions !== null) {
            $this->childNodes[self::PARTITION_OPTIONS] = $partitionOptions;
        }
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        return new self(
            $array[self::TABLE_NAME] ?? '',
            ArrayTyped::unserializeList($array[self::ALTER_OPTIONS] ?? [], $validator),
            isset($array[self::PARTITION_OPTIONS]) ? ArrayTyped::unserialize($array[self::PARTITION_OPTIONS], $validator) : null
        );
    }

    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        $result = [
            self::TABLE_NAME    => $this->tableName,
            self::ALTER_OPTIONS => ArrayTyped::serializeList($validator, ...($this->childNodes[self::ALTER_OPTIONS]?->getChildNodes() ?? [])),
        ];

        if ($this->partitionOptions !== null) {
            $result[self::PARTITION_OPTIONS] = ArrayTyped::serialize($this->partitionOptions, $validator);
        }

        return $result;
    }

    #[\Override]
    public function getTableName(): string
    {
        return $this->tableName;
    }


    #[\Override]
    public function setTableName(string $tableName): static
    {
        $this->tableName            = $tableName;
        return $this;
    }

    #[\Override]
    protected function generateResult(): string
    {
        $sql                        = [];
        $sql[]                      = /** @lang aql */'ALTER TABLE ' . $this->escape($this->tableName);

        if ($this->childNodes !== []) {
            $sql[]                  = $this->childNodesToAql("\n");
        }

        return \implode("\n", $sql);
    }

    #[\Override]
    public function addAlterOption(AlterOptionInterface $alterOption): static
    {
        // TODO: Implement addAlterOption() method.
    }
}
