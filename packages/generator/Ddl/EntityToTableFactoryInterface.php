<?php

declare(strict_types=1);

namespace IfCastle\AQL\Generator\Ddl;

use IfCastle\AQL\Entity\EntityInterface;

interface EntityToTableFactoryInterface
{
    public function newEntityToTableGenerator(EntityInterface $entity): EntityToTableInterface;
}
