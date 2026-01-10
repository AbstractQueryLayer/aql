<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;

/**
 * ## IndexDefinition.
 *
 * Expression like:
 * UNIQUE KEY `api_users_id` (`api_users_id`,`api_groups_id`),
 * or
 * PRIMARY KEY (`id`),
 * or
 * KEY `api_users_groups_fk0` (`api_users_id`),
 *
 */
class IndexDefinition extends DdlStatementAbstract implements IndexDefinitionInterface
{
    public function __construct(
        protected ?string $indexName    = null,
        protected ?string $indexType    = null,
        protected ?string $indexRole    = null,
        protected array $indexParts     = []
    ) {
        parent::__construct();
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        return new self(
            $array[self::INDEX_NAME] ?? null,
            $array[self::INDEX_TYPE] ?? null,
            $array[self::INDEX_ROLE] ?? null,
            $array[self::INDEX_PARTS] ?? []
        );
    }

    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        return [
            self::INDEX_NAME            => $this->indexName,
            self::INDEX_TYPE            => $this->indexType,
            self::INDEX_ROLE            => $this->indexRole,
            self::INDEX_PARTS           => $this->indexParts,
        ];
    }

    #[\Override]
    public function getIndexName(): ?string
    {
        return $this->indexName;
    }

    #[\Override]
    public function getIndexType(): ?string
    {
        return $this->indexType;
    }

    #[\Override]
    public function getIndexRole(): ?string
    {
        return $this->indexRole;
    }


    #[\Override]
    public function setIndexRole(string $indexRole): static
    {
        $this->indexRole            = $indexRole;

        return $this;
    }

    #[\Override]
    public function getIndexParts(): array
    {
        return $this->indexParts;
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

        if ($this->indexRole !== null) {
            $sql[]                  = $this->indexRole;
        }

        $sql[]                      = 'KEY';

        if ($this->indexName !== null) {
            $sql[]                  = $this->escape($this->indexName);
        }

        if ($this->indexType !== null) {
            $sql[]                  = $this->indexType;
        }

        if ($this->indexParts !== []) {
            $parts                  = [];

            foreach ($this->indexParts as $index) {
                $parts[]            = $this->escape($index);
            }

            $sql[]                  = '(' . \implode(',', $parts) . ')';
        }

        return \implode(' ', $sql);
    }
}
