<?php

declare(strict_types=1);

namespace IfCastle\AQL\Generator\Ddl;

interface EntityToTableAfterInterface
{
    public function handleEntityToTableAfter(EntityToTableInterface $generator): void;
}
