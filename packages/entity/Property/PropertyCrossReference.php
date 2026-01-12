<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

use IfCastle\AQL\Dsl\Node\Exceptions\NodeException;
use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Join;
use IfCastle\AQL\Dsl\Sql\Query\Expression\ValueList;
use IfCastle\AQL\Dsl\Sql\Query\Select;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumnInterface;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\AQL\Entity\Relation\DirectRelationInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\Context\PropertyContextInterface;
use IfCastle\AQL\Executor\Resolver\KeyValuesResolver;
use IfCastle\AQL\Executor\ResultComposer\ComposeByColumnAndKey;
use IfCastle\Exceptions\RequiredValueEmpty;
use IfCastle\Exceptions\UnexpectedValueType;

/**
 * Virtual property that is used to use a collection value from cross-reference entity.
 */
class PropertyCrossReference extends VirtualProperty
{
    public function __construct(string $name, protected string $toEntityName, protected string $crossReferenceEntityName)
    {
        parent::__construct($name, self::T_TUPLE, [
            NodeContextInterface::CONTEXT_TUPLE  => $this->handleTuple(...),
            NodeContextInterface::CONTEXT_FILTER => $this->handleFilter(...),
            NodeContextInterface::CONTEXT_ASSIGN => $this->handleAssign(...),
        ]);
    }

    /**
     * @throws UnexpectedValueType
     * @throws EntityDescriptorException
     * @throws RequiredValueEmpty
     * @throws ParseException
     * @throws NodeException
     */
    protected function handleTuple(PropertyContextInterface $context): bool
    {
        // Tuple with cross-reference entity strategy:
        // 1. Get all primary keys from fromEntity
        // 2. Make one Select query for all primary keys
        // 3. Compose results into tuple

        $crossReferenceRelation         = $context->getCurrentEntity()->getRelation($this->crossReferenceEntityName);

        if ($crossReferenceRelation instanceof DirectRelationInterface === false) {
            throw new UnexpectedValueType('$crossReferenceRelation', $crossReferenceRelation, DirectRelationInterface::class);
        }

        $crossReferenceEntity           = $context->getEntity($this->crossReferenceEntityName);
        $toEntityRelation               = $crossReferenceEntity->getRelation($this->toEntityName);

        if ($toEntityRelation instanceof DirectRelationInterface === false) {
            throw new UnexpectedValueType('$toEntityRelation', $toEntityRelation, DirectRelationInterface::class);
        }

        // Make this column as placeholder
        // like '' as `alias`
        // So we get SQL result with empty column
        $context->getTupleColumn()->markAsPlaceholder();

        // Contains primary keys tuple columns from the main entity
        /** @var TupleColumnInterface[] $fromEntityKeyColumns */
        $fromEntityKeyColumns           = [];

        foreach ($crossReferenceRelation->getLeftKey()->getKeyColumns() as $keyColumn) {
            $fromEntityKeyColumns[]     = $context->resolveHiddenColumn(new Column($keyColumn));
        }

        // Make sql query like:
        // SELECT * FROM `author` as `t0`
        // INNER JOIN `book_to_author` as `t1` ON (`t1`.`authorId` = `t0`.`id`)
        // WHERE `t1`.`authorId` IN ('1', '2', '3')

        $query                          = new Select($this->toEntityName);
        $query->getFrom()->addJoin(new Join(Join::INNER, $this->crossReferenceEntityName));
        $valueList = ValueList::in(
            ...
            \array_map(static fn(string $column) => new Column($column, $crossReferenceEntity->getEntityName()),
                $crossReferenceRelation->getRightKey()->getKeyColumns())
        );

        $query->getWhere()->add($valueList);

        $toEntityKeyColumns             = [];

        foreach ($crossReferenceRelation->getRightKey()->getKeyColumns() as $keyColumn) {
            $toEntityKeyColumns[]       = $query->getTuple()->resolveHiddenColumn(new Column($keyColumn, $crossReferenceEntity->getEntityName()));
        }

        $context->getQueryCommand()->getResultProcessingPlan()
                                   ->addResultReader(new KeyValuesResolver($valueList, ...$fromEntityKeyColumns));

        $sqlCommand                     = $context->getQueryExecutor()->getQueryPlan()->newQueryLeftCommand($query);

        ComposeByColumnAndKey::compose(
            $context->getTupleColumn()->getAliasOrColumnName(),
            \array_map(static fn($column) => $column->getAliasOrColumnName(), $fromEntityKeyColumns),
            \array_map(static fn($column) => $column->getAliasOrColumnName(), $toEntityKeyColumns),
            $sqlCommand->getResultProcessingPlan(),
            $context->getQueryExecutor()->getExecutionPlan(),
        );

        return true;
    }


    protected function handleFilter(PropertyContextInterface $context): bool {}

    protected function handleAssign(PropertyContextInterface $context): bool {}
}
