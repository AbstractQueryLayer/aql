<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Node\Exceptions\NodeException;
use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\Conditions;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperationInterface;
use IfCastle\AQL\Dsl\Sql\Query\SubqueryInterface;

/**
 * Expression for WHERE.
 *
 * SELECT * FROM Entity WHERE ENTITY Entity2(is_active = true)
 *
 * This expression is equivalent to:
 * SELECT * FROM Entity WHERE Entity.entity2_id IN (SELECT @id FROM Entity2 WHERE is_active = true)
 * however, in this case, you do not need to specify relationships between entities!
 *
 * Other options:
 *
 * SELECT * FROM Entity WHERE ENTITY EXCLUDE Entity2(is_active = true)
 *
 * multiple relations
 *
 * SELECT * FROM Entity WHERE
 *          Entity.is_active = 1 AND
 *          (ENTITY Entity2(lang = 'ar') OR ENTITY Entity2(default_lang = 'ar'))
 *
 *
 */
class WhereEntity extends NodeAbstract
{
    public const string WHERE_ENTITY = 'WHERE_ENTITY';

    public const string ENTITY = 'ENTITY';

    public const string EXCLUDE = 'EXCLUDE';

    public const string NODE_CONDITIONS = 'c';

    /**
     * Create WhereEntity for nested relations.
     *
     *
     */
    public static function newNested(string $entityName, ?ConditionsInterface $conditions = null, bool $isExclude = false): WhereEntity
    {
        $whereEntity                = new static($entityName, $conditions, $isExclude);
        $whereEntity->forbidIndirectRelation = true;

        return $whereEntity;
    }

    public static function findWhereEntity(string $entityName, NodeInterface ...$nodes): ?self
    {
        foreach ($nodes as $node) {

            // Also try to use substitution in the first level
            $node                   = $node->matchNodeByType(self::class);

            if ($node instanceof self && $node->getEntityName() === $entityName) {
                return $node;
            }
        }

        return null;
    }

    protected string $nodeName      = self::WHERE_ENTITY;

    protected bool $forbidIndirectRelation = false;

    public function __construct(protected string $entityName, ?ConditionsInterface $conditions = null, protected bool $isExclude = false)
    {
        parent::__construct();

        $this->childNodes[self::NODE_CONDITIONS] = $conditions;
    }

    public function isExclude(): bool
    {
        return $this->isExclude;
    }

    public function isForbidIndirectRelation(): bool
    {
        return $this->forbidIndirectRelation;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $conditions                 = $this->getConditions() !== null ? '(' . $this->getConditions()->getAql($forResolved) . ')' : '';
        $exclude                    = $this->isExclude ? static::EXCLUDE . ' ' : '';

        return static::ENTITY . ' ' . $exclude . $this->entityName . $conditions;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getConditions(): ConditionsInterface|null
    {
        return $this->childNodes[self::NODE_CONDITIONS];
    }

    public function conditions(): ConditionsInterface
    {
        if ($this->getConditions() === null) {
            $this->childNodes[self::NODE_CONDITIONS] = new Conditions();
        }

        return $this->getConditions();
    }

    public function setConditions(ConditionsInterface $conditions): static
    {
        $this->childNodes[self::NODE_CONDITIONS] = $conditions;
        return $this;
    }

    /**
     * Returns Subquery from expression.
     *
     * @throws  NodeException
     */
    public function extractSubquery(): SubqueryInterface
    {
        $this->throwOutOfHandled();
        if ($this->substitution instanceof WhereEntity) {
            return $this->substitution->extractSubquery();
        }

        if ($this->substitution instanceof LROperationInterface) {
            return $this->substitution->getRightNode();
        }

        throw new NodeException($this, 'Unknown type of substitution object ');

    }

    #[\Override]
    public function shouldInheritContext(): bool
    {
        return true;
    }

    #[\Override]
    protected function generateResult(): string
    {
        return '';
    }
}
