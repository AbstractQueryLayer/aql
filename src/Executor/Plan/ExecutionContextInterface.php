<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Transaction\WithTransactionInterface;
use IfCastle\DI\ContainerMutableInterface;

interface ExecutionContextInterface extends ContainerMutableInterface, ExecutionPlanAwareInterface, WithTransactionInterface
{
    public function getResult(): ?ResultInterface;

    public function setResult(ResultInterface $result): void;

    public function getStage(): ?string;

    public function setStage(string $stage): void;

    public function getParentExecutionContext(): ?ExecutionContextInterface;
}
