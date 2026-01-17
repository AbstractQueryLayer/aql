<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Relation;

use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\TupleConditions;
use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\ColumnList;
use IfCastle\AQL\Entity\Key\KeyInterface;

/**
 * ## DirectRelation.
 *
 * Relation like:
 * SELECT * FROM leftEntity
 * JOIN rightEntity ON (rightEntity.rightKey = leftEntity.leftKey)
 *
 */
class DirectRelation extends RelationAbstract implements DirectRelationInterface
{
    public function __construct(
        string                 $leftEntity,
        protected KeyInterface $leftKey,
        string                 $rightEntity,
        protected KeyInterface $rightKey,
        string                 $type = self::REFERENCE
    ) {
        parent::__construct();
        $this->leftEntityName       = $leftEntity;
        $this->rightEntityName      = $rightEntity;
        $this->type                 = $type;
    }

    #[\Override]
    public function getLeftKey(): KeyInterface
    {
        return $this->leftKey;
    }

    #[\Override]
    public function getRightKey(): KeyInterface
    {
        return $this->rightKey;
    }

    #[\Override]
    public function reverseRelation(): ?static
    {
        $reversed                   = new self(
            $this->rightEntityName,
            clone $this->rightKey,
            $this->leftEntityName,
            clone $this->leftKey,
            self::reverseType($this->type)
        );

        $reversed->isLeastOnce      = $this->isLeastOnce;
        $reversed->isRequired       = $this->isRequired;

        if ($this->conditions !== null) {
            $reversed->conditions   = $this->conditions->reverseConditions();
        }

        return $reversed;
    }

    #[\Override]
    public function generateConditions(): ConditionsInterface
    {
        //
        // Generate expression like this:
        //
        // JOIN rightEntityName ON (rightEntityName.rightKey = leftEntityName.leftKey)
        // conditions--------------^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
        //

        $conditions                 = $this->conditions ?? new TupleConditions(ConditionsInterface::TYPE_AND, $this->rightEntityName);

        // Simple code if key contains one field
        if ($this->leftKey->isKeySimple()) {
            $conditions->equal(new Column($this->rightKey->getKeyColumns()[0], $this->rightEntityName),
                new Column($this->leftKey->getKeyColumns()[0], $this->leftEntityName, true));

            return $conditions;
        }

        $leftColumns                = $this->leftKey->getKeyColumns();
        $rightColumns               = $this->rightKey->getKeyColumns();

        if (\count($leftColumns) !== \count($rightColumns)) {
            throw new TransformationException([
                'template'          => 'Right {rightEntityName} and left {leftEntityName} keys are not identical in number of fields',
                'rightEntityName'   => $this->rightEntityName,
                'leftEntityName'    => $this->leftEntityName,
            ]);
        }

        //
        // Expression like this:
        // (key1, key2, ...) = (foreign_key1, foreign_key2, ...)
        //
        $conditions->equal(
            new ColumnList(...\array_map(fn(string $column) => new Column($column, $this->rightEntityName), $leftColumns)),
            new ColumnList(...\array_map(fn(string $column) => new Column($column, $this->leftEntityName, true), $rightColumns))
        );

        return $conditions;
    }
}
