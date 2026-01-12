<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Relation;

use IfCastle\AQL\Dsl\Sql\Conditions\Conditions;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\Exceptions\UnexpectedValue;

class IndirectRelation extends RelationAbstract implements IndirectRelationInterface
{
    protected array $entitiesPath   = [];

    public function __construct(string ...$entitiesPath)
    {
        if (\count($entitiesPath) < 3) {
            throw new UnexpectedValue('$entitiesPath', $entitiesPath, 'entitiesPath should be at least 3 entities');
        }

        parent::__construct();
        $this->entitiesPath         = $entitiesPath;
        $this->leftEntityName       = $entitiesPath[\array_key_first($entitiesPath)];
        $this->rightEntityName      = $entitiesPath[\array_key_last($entitiesPath)];
        $this->type                 = self::ASSOCIATION;
    }

    #[\Override]
    public function getEntitiesPath(): array
    {
        return $this->entitiesPath;
    }

    /**
     * @throws UnexpectedValue
     */
    #[\Override]
    public function reverseRelation(): ?static
    {
        $relation                   = new static(...\array_reverse($this->entitiesPath));
        $relation->isRequired       = $this->isRequired;
        $relation->isConsistent     = $this->isConsistent;
        $relation->isLeastOnce      = $this->isLeastOnce;

        if ($this->conditions !== null) {
            $relation->conditions   = $this->conditions->reverseConditions();
        }

        return $relation;
    }

    #[\Override]
    public function cloneWithSubjects(?string $leftEntityName = null, ?string $rightEntityName = null): static
    {
        $clone                      = parent::cloneWithSubjects($leftEntityName, $rightEntityName);
        $clone->entitiesPath[\array_key_first($this->entitiesPath)] = $leftEntityName ?? $this->leftEntityName;
        $clone->entitiesPath[\array_key_last($this->entitiesPath)] = $rightEntityName ?? $this->rightEntityName;

        return $clone;
    }

    #[\Override]
    public function generateConditions(): ConditionsInterface
    {
        return new Conditions();
    }
}
