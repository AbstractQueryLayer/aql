<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\PostAction;

use IfCastle\DesignPatterns\Handler\InvokableInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableInterface;

interface PostActionInterface extends InvokableInterface, ArraySerializableInterface {}
