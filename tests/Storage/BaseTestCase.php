<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

use IfCastle\DI\ConfigInterface;
use IfCastle\DI\ConfigMutable;
use IfCastle\DI\ConfigMutableInterface;
use IfCastle\DI\ContainerBuilder;
use IfCastle\DI\ContainerInterface;
use IfCastle\DI\Resolver;
use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase
{
    protected ?ContainerInterface $diContainer = null;

    protected function getDiContainer(): ?ContainerInterface
    {
        return $this->diContainer;
    }

    protected function getConfigMutable(): ConfigMutableInterface
    {
        return $this->diContainer->resolveDependency(ConfigInterface::class);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->defineContainer();
    }

    private function defineContainer(): void
    {
        $containerBuilder           = new ContainerBuilder(resolveScalarAsConfig: false);
        $containerBuilder->bindSelfReference();
        $containerBuilder->bindObject(ConfigInterface::class, new ConfigMutable());

        $this->diContainer          = $containerBuilder->buildContainer(new Resolver());
    }
}
