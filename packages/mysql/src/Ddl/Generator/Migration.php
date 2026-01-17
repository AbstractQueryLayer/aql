<?php

declare(strict_types=1);

namespace IfCastle\AQL\MySql\Ddl\Generator;

use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;

class Migration
{
    public function __construct(protected EntityInterface $entity, protected EntityFactoryInterface $entityFactory) {}
}
