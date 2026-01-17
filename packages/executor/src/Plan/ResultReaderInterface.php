<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\AQL\Result\ResultInterface;
use IfCastle\DesignPatterns\Handler\InvokableInterface;

interface ResultReaderInterface extends InvokableInterface
{
    public function readResult(ResultInterface $result): void;
}
