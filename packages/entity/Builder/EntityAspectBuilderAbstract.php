<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Builder;

abstract class EntityAspectBuilderAbstract implements EntityAspectBuilderInterface
{
    protected array $builderConfig  = [];

    #[\Override]
    public function setAspectBuilderConfig(array $config): static
    {
        $this->builderConfig        = $config;
        return $this;
    }
}
