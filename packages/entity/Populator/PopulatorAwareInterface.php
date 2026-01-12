<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Populator;

interface PopulatorAwareInterface
{
    public function getPopulator(): PopulatorInterface;
}
