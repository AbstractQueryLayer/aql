<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Scope;

use IfCastle\AQL\Executor\ColumnHandlerInterface;
use IfCastle\AQL\Executor\FunctionHandlerInterface;
use IfCastle\AQL\Executor\OptionHandlerInterface;
use IfCastle\AQL\Executor\QueryHandlerInterface;
use IfCastle\AQL\Executor\SubjectHandlerInterface;

interface EntityScopeRulesInterface extends QueryHandlerInterface,
    ColumnHandlerInterface,
    FunctionHandlerInterface,
    OptionHandlerInterface,
    SubjectHandlerInterface {}
