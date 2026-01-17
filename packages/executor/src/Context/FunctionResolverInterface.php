<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Context;

use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Entity\Functions\FunctionInterface;

interface FunctionResolverInterface
{
    public function resolveFunction(FunctionReferenceInterface|string $functionReference, ?NodeContextInterface $context = null): ?FunctionInterface;
}
