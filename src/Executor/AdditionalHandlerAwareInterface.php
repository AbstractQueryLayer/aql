<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

interface AdditionalHandlerAwareInterface
{
    public function getAdditionalHandler(): mixed;
}
