<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Context;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumnInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Property\PropertyInterface;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use IfCastle\Exceptions\UnexpectedValueType;

interface PropertyContextInterface extends NodeContextInterface
{
    /**
     * Return column expression.
     */
    public function getColumn(): ColumnInterface;

    public function getTupleColumn(): ?TupleColumnInterface;

    /**
     * Return column's entity.
     */
    public function getOwnerEntity(): EntityInterface;

    /**
     * Returns current expression for this property.
     */
    public function getPropertyExpression(): ?NodeInterface;

    /**
     * The method replaces the property-reference to the $node-expression.
     * If $ifColumn is TRUE, the method takes effect only if the property-reference is a column.
     *
     *
     * @return  $this
     *
     * @throws UnexpectedValueType
     */
    public function substituteColumn(NodeInterface $node, bool $ifColumn = true): static;

    /**
     * The method replaces the property-reference to the empty expression (i.e., remove the column from the tuple).
     *
     * @param    bool    $ifColumn        *
     *
     * @return $this
     */
    public function substituteColumnToNull(bool $ifColumn = true): static;

    /**
     * The method returns the right side expression if it is a constant.
     * If it is not, the method returns NULL or an exception if a parameter $isThrow is TRUE.
     *
     *
     *
     * @throws  QueryException|UnexpectedValueType
     */
    public function getRightConstant(bool $isThrow = true): ?ConstantInterface;

    /**
     * Returns the value of the right side
     * The method returns the value of the constant right expression, if it exists.
     *
     *
     * @throws  QueryException|UnexpectedValueType
     */
    public function getRightValue(bool $isThrow = true): mixed;

    /**
     * The method replaces the right side of the $node expression, if it exists.
     *
     * @return  $this
     * @throws  QueryException|UnexpectedValueType
     */
    public function substituteRightSide(NodeInterface $node): static;

    /**
     * The method processes the right side expression through a callback function.
     *
     * @return  $this
     *
     * @throws QueryException
     * @throws UnexpectedValueType
     */
    public function handleRightValue(callable $valueHandler): static;

    public function findAssignValues(): array;

    public function handleAssignValues(callable $handler): void;

    public function getSubjectAlias(): string;

    /**
     *
     * @return  $this
     * @throws UnexpectedValueType
     */
    public function substituteWithSql(string $sql): static;

    public function getTuple(): TupleInterface;

    /**
     * Adds a hidden column to the query result.
     *
     * @throws
     */
    public function addHiddenColumn(NodeInterface $column): TupleColumnInterface;

    public function resolveHiddenColumn(ColumnInterface $column): TupleColumnInterface;

    public function addUnSerializedColumn(TupleColumnInterface $tupleColumn, PropertyInterface $property): static;
}
