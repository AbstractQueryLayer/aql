<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Functions;

use IfCastle\AQL\Aspects\AccessControl\AccessByGroupsInterface;
use IfCastle\AQL\Aspects\AccessControl\AccessByGroupsMutableInterface;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Executor\FunctionHandlerInterface;

interface FunctionInterface extends FunctionHandlerInterface, AccessByGroupsInterface, AccessByGroupsMutableInterface
{
    public static function functionReference(): FunctionReferenceInterface;

    public function getFunctionName(): string;

    public function getFunctionReference(): FunctionReferenceInterface;

    public function isCompatibleWith(FunctionReferenceInterface $function): bool;

    /**
     * Allow for external functions to be called.
     */
    public function isFunctionPublic(): bool;
}
