<?php

declare(strict_types=1);

namespace IfCastle\AQL\Generator\Ddl;

interface EntityToTableBeforeInterface
{
    public function handleEntityToTableBefore(EntityToTableInterface $generator): void;
}
