<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Functions;

interface TriggerInterface
{
    public function getTriggerName(): string;
}
