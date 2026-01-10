<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Builder;

use IfCastle\AOP\AspectBuilderClassAwareInterface;
use IfCastle\AOP\AspectDescriptorInterface;
use IfCastle\DI\ConfigInterface;
use IfCastle\DI\FromConfig;
use IfCastle\DI\FromRegistry;
use IfCastle\Exceptions\ClassNotExist;
use IfCastle\Exceptions\UnexpectedValueType;

/**
 * Abstract class for overriding builders and builder configs.
 */
class EntityAspectBuilderFactory implements EntityAspectBuilderFactoryInterface
{
    public function __construct(
        #[FromRegistry(EntityAspectBuilderFactoryInterface::class)]
        protected ConfigInterface|null $registry = null,
        #[FromConfig('entity.builder')]
        protected array|null $config = null
    ) {}

    /**
     * @var EntityAspectBuilderInterface[]
     */
    protected array $builders       = [];

    /**
     * @throws UnexpectedValueType
     * @throws ClassNotExist
     */
    #[\Override]
    public function getEntityAspectBuilder(AspectDescriptorInterface $aspectDescriptor): EntityAspectBuilderInterface
    {
        $aspectName                 = $aspectDescriptor->getAspectName();

        if ($aspectDescriptor instanceof AspectBuilderClassAwareInterface) {
            return $this->instantiateBuilder($aspectDescriptor->getAspectBuilderClass(), $aspectName);
        }

        if (\array_key_exists($aspectName, $this->builders)) {
            return $this->builders[$aspectName];
        }

        foreach ($this->registry?->findSection('namespaces') as $namespace) {
            $class                  = $namespace . '\\' . $aspectName . 'Builder';

            if (\class_exists($class)) {

                $builder            = $this->instantiateBuilder($class, $aspectName);

                $this->builders[$aspectName] = $builder;

                return $builder;
            }
        }

        throw new ClassNotExist($aspectName);
    }

    /**
     * @throws UnexpectedValueType
     * @throws ClassNotExist
     */
    protected function instantiateBuilder(string $builderClass, string $aspectName): EntityAspectBuilderInterface
    {
        if (false === \class_exists($builderClass)) {
            throw new ClassNotExist($builderClass);
        }

        $builder                    = new $builderClass();

        if ($builder instanceof EntityAspectBuilderInterface === false) {
            throw new UnexpectedValueType('builder', $builder, EntityAspectBuilderInterface::class);
        }

        $builder->setAspectBuilderConfig($this->pickUpConfig($aspectName));

        return $builder;
    }

    protected function pickUpConfig(string $aspectName): array
    {
        return $this->config[$aspectName] ?? [];
    }
}
