<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\DesignPatterns\Handler\InvokableInterface;

interface RowModifierInterface extends InvokableInterface
{
    public function modifyResultRows(array &$rows, ExecutionContextInterface $context): void;
}
