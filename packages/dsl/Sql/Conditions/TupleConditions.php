<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Conditions;

use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantTupleInterface;
use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\ColumnList;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\Equal;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperationInterface;

class TupleConditions extends Conditions implements TupleConditionsInterface
{
    protected array|null $leftColumns = null;

    protected array|null $rightColumns = null;

    #[\Override]
    public function getLeftColumns(): array
    {
        if ($this->leftColumns !== null) {
            return $this->leftColumns;
        }

        $this->calculateNodes();

        return $this->leftColumns;
    }

    #[\Override]
    public function getRightColumns(): array
    {
        if ($this->rightColumns !== null) {
            return $this->rightColumns;
        }

        $this->calculateNodes();

        return $this->rightColumns;
    }

    #[\Override]
    public function substituteRightExpression(ConstantInterface|ConstantTupleInterface $constantExpression): static
    {
        if ($this->rightColumns === null) {
            $this->calculateNodes();
        }
        if (\count($this->rightColumns) !== 1) {
            throw new TransformationException([
                'template'      => 'Cannot substitute right expression with a single constant. '
                                   . 'Expected a tuple of constants in {aql}',
                'aql'           => $this->getAql(),
            ]);
        }
        $this->rightColumns[0]->setSubstitution($constantExpression);
        return $this;
    }

    protected function calculateNodes(): void
    {
        $this->leftColumns          = [];
        $this->rightColumns         = [];

        foreach ($this->childNodes as $operation) {
            if ($operation instanceof LROperationInterface) {
                $leftKeys           = $operation->getLeftKeys();
                $rightKeys          = $operation->getForeignKeys();

                //
                // We consider only tuple conditions where both sides are keys.
                // If one of the sides is a key and the other is a constant, we skip this operation.
                //
                if ($leftKeys !== [] && $rightKeys !== []) {

                    if ($operation->getOperation() !== LROperationInterface::EQU) {
                        throw new TransformationException([
                            'template'  => 'Only equal operation is allowed for tuple conditions. Got: {operation} in {aql}',
                            'operation' => $operation->getOperation(),
                            'aql'       => $operation->getAql(),
                        ]);
                    }

                    $this->leftColumns      += $leftKeys;
                    $this->rightColumns     += $rightKeys;
                }
            }
        }
    }

    protected function transformToCompositeIfNeed(ConstantTupleInterface $constantTuple): void
    {
        $foundComposite             = false;
        $resultLeftColumns          = [];
        $firstOperation             = null;

        foreach ($this->childNodes as $operation) {
            if ($operation instanceof LROperationInterface) {

                $leftKeys           = $operation->getLeftKeys();
                $rightKeys          = $operation->getForeignKeys();

                if ($leftKeys === [] || $rightKeys === []) {
                    continue;
                }

                if ($firstOperation === null) {
                    $firstOperation = $operation;
                }

                if ($operation->isCompositeComparison()) {

                    if ($foundComposite) {
                        throw new TransformationException([
                            'template'  => 'Cannot handle more than one composite condition in {aql}',
                            'aql'       => $this->getAql(),
                        ]);
                    }

                    $foundComposite = true;
                } else {

                    if ($foundComposite) {
                        throw new TransformationException([
                            'template'  => 'Cannot handle a mix of composite and non-composite conditions in {aql}',
                            'aql'       => $this->getAql(),
                        ]);
                    }

                    // eliminate the non-composite condition
                    $operation->substituteToNullNode();
                }

                $resultLeftColumns      += $leftKeys;
            }
        }

        if ($firstOperation === null || $resultLeftColumns === []) {
            return;
        }

        // Substitute right side with a constant tuple
        if ($firstOperation->isCompositeComparison()) {
            $firstOperation->getRightNode()->setSubstitution($constantTuple);
            return;
        }

        // Substitute first operation with a composite comparison
        $firstOperation->setSubstitution(new Equal(new ColumnList(...$resultLeftColumns), $constantTuple));
    }
}
