<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Functions;

use IfCastle\AQL\Aspects\AccessControl\AccessByGroups\AccessByGroupsMutableInterface;
use IfCastle\AQL\Aspects\AccessControl\AccessByGroups\AccessByGroupsTrait;
use IfCastle\AQL\Dsl\Node\NodeHelper;
use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReference;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Dsl\Sql\Helpers\TupleHelper;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\Exceptions\FunctionParameterWrong;
use IfCastle\AQL\Executor\Exceptions\FunctionWrongUse;
use IfCastle\AQL\Executor\Exceptions\QueryException;
use IfCastle\AQL\Executor\Plan\RowModifier;

/**
 * ## TupleColumnTransformerAbstract.
 *
 * Class for defining a virtual function that changes the value of a column, processing it after the request.
 * Example:
 * isAllColumnsEqual compares all columns in the tuple and returns true if all columns are equal.
 * ```sql
 * SELECT isAllColumnsEqual('some parameter') FROM table
 * ```
 * Returns:
 * ```json
 * [
 * {
 * "isAllColumnsEqual": true
 * }
 * ]
 * ```
 */
abstract class TupleColumnTransformerAbstract implements FunctionInterface, AccessByGroupsMutableInterface
{
    use AccessByGroupsTrait;

    /**
     * @throws FunctionWrongUse
     * @throws QueryException
     */
    #[\Override]
    public function handleFunction(FunctionReferenceInterface $function, NodeContextInterface $context): void
    {
        // We can use this function only in tuple
        if (false === NodeHelper::inTuple($function)) {
            throw new FunctionWrongUse($function->getEntityName() ?? '', $this->getFunctionName(), 'The function can be used only in tuple');
        }

        //
        // We are replacing the expression myFunction(parameters) to someExpression
        //

        // Extract parameters
        $parameters                 = $this->extractParameters($function);

        $this->validateParameters($parameters, $function);

        $columnAlias                = TupleHelper::substituteTupleExpression(
            $this->createSubstitution($parameters),
            $function,
            $context->getCurrentNode(),
            $this->getFunctionName()
        )->getAliasOrColumnName();

        // Processing the received data from the database.
        $context->getResultProcessingPlan()->addRowModifier(
            new RowModifier(fn(array &$rows) => $this->transformRows($rows, $columnAlias, $parameters))
        );
    }

    #[\Override]
    public function getFunctionReference(): FunctionReferenceInterface
    {
        return FunctionReference::virtual($this->getFunctionName());
    }

    #[\Override]
    public function isCompatibleWith(FunctionReferenceInterface $function): bool
    {
        return $function->getFunctionName() === $this->getFunctionName() && $function->isVirtual() && false === $function->isGlobal();
    }

    #[\Override]
    public function isFunctionPublic(): bool
    {
        return false;
    }

    /**
     * @throws FunctionParameterWrong
     */
    protected function extractParameters(FunctionReferenceInterface $function): array
    {
        $parameters                 = [];
        $index                      = 0;

        foreach ($function->getFunctionParameters() as $parameter) {

            ++$index;

            if ($parameter instanceof ConstantInterface === false) {
                throw new FunctionParameterWrong(
                    $function->getEntityName() ?? '',
                    $this->getFunctionName(),
                    '#' . $index,
                    'The function must have only ConstantInterface parameters, got: ' . \get_debug_type($parameter)
                );
            }

            $parameters[]           = $parameter->getConstantValue();
        }

        return $parameters;
    }

    protected function validateParameters(array $parameters, FunctionReferenceInterface $function): void {}

    protected function transformRows(array &$rows, string $columnAlias, array $parameters): void
    {
        foreach ($rows as &$row) {
            if (\array_key_exists($columnAlias, $row)) {
                $row[$columnAlias]      = $this->transformRow($row[$columnAlias], $parameters);
            }
        }

        unset($row);
    }

    /**
     * @param string[] $accessGroups
     *
     * @return  $this
     */
    #[\Override]
    public function setAccessGroups(string ...$accessGroups): static
    {
        $this->accessGroups         = $accessGroups;
        return $this;
    }

    abstract protected function createSubstitution(array $parameters): NodeInterface;

    abstract protected function transformRow(mixed $value, array $parameters): mixed;
}
