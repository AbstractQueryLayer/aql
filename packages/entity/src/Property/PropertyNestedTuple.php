<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

use IfCastle\AQL\Dsl\Node\Exceptions\NodeException;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Tuple\NestedTuple;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumn;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\Context\PropertyContextInterface;
use IfCastle\Exceptions\RequiredValueEmpty;
use IfCastle\TypeDefinitions\DefinitionMutableInterface;
use IfCastle\TypeDefinitions\TypeObject;

class PropertyNestedTuple extends VirtualProperty
{
    protected array $columns;

    public function __construct(string $name, protected string $fromEntityName, string ...$columns)
    {
        parent::__construct($name, self::T_OBJECT, [
            NodeContextInterface::CONTEXT_TUPLE  => $this->handleTuple(...),
        ]);

        $this->columns              = $columns;
    }

    /**
     * @throws RequiredValueEmpty
     */
    #[\Override]
    public function getDefinition(?EntityFactoryInterface $entitiesFactory = null): DefinitionMutableInterface
    {
        if ($entitiesFactory === null) {
            throw new RequiredValueEmpty('entitiesFactory');
        }

        $definition                 = new TypeObject($this->getName());
        $entity                     = $entitiesFactory?->getEntity($this->fromEntityName);

        foreach ($this->columns as $column) {
            $definition->describe($entity->getProperty($column)->getDefinition());
        }

        return $definition;
    }

    /**
     * @throws NodeException
     * @throws RequiredValueEmpty
     */
    protected function handleTuple(PropertyContextInterface $context): bool
    {
        $columns                    = [];

        foreach ($this->columns as $column) {
            $columns[]              = new TupleColumn(new Column($column));
        }

        $nestedTuple                = new NestedTuple($this->fromEntityName, $columns);

        $context->getColumn()->setSubstitution($nestedTuple);
        $context->getTupleColumn()->setAliasIfUndefined($this->getName());

        return true;
    }
}
