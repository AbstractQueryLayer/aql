<?php

declare(strict_types=1);

namespace IfCastle\AQL\Generator\Ddl;

interface EntityToTableAwareInterface
{
    public function getEntityToTableGenerator(): EntityToTableInterface;
}
