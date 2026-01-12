<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Builder\NamingStrategy;

interface NamingStrategyAwareInterface
{
    public function getNamingStrategy(): NamingStrategyInterface;
}
