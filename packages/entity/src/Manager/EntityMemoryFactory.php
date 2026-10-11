<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Manager;

use IfCastle\AQL\Entity\BlankEntity;
use IfCastle\AQL\Entity\Builder\EntityBuilderInterface;
use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\AQL\Executor\Exceptions\EntityNotFound;
use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\DI\LazyLoader;
use IfCastle\Exceptions\BaseException;
use IfCastle\Exceptions\UnexpectedValueType;

class EntityMemoryFactory implements
    EntityStorageInterface,
    EntityDescriptorFactoryInterface
{
    public function __construct(
        protected array $entityNamespaces,
        protected ContainerInterface $container,
        protected EntityBuilderInterface|LazyLoader $entityBuilder,
        protected ?string $blankEntityClass  = null,
    ) {
        if ($this->entityBuilder instanceof LazyLoader) {
            $this->entityBuilder->setAfterHandler(fn($value) => $this->entityBuilder = $value);
        }
    }

    /**
     * @var EntityInterface[]
     */
    protected array $entities       = [];

    /**
     * @var EntityInterface[]
     */
    protected array $typicalEntities    = [];

    /**
     * Names of the entities whose full build is running, in the order their builds started.
     * @var array<string, true>
     */
    protected array $building           = [];

    /**
     *
     * @throws  BaseException
     * @throws  EntityNotFound
     */
    #[\Override]
    public function getEntityDescriptor(string $entityName, bool $isRaw = false): EntityInterface & EntityDescriptorInterface
    {
        $entityName                 = \ucfirst($entityName);

        if (\array_key_exists($entityName, $this->entities)) {
            return $this->entities[$entityName];
        }

        if (\str_starts_with($entityName, self::TYPICAL_PREFIX)) {

            $entityName             = \substr($entityName, 1);

            if (\array_key_exists($entityName, $this->typicalEntities)) {
                return $this->typicalEntities[$entityName];
            }

            throw new EntityNotFound($entityName);
        }

        $entity                     = $this->loadEntity($entityName, $isRaw);

        if (false === $entity->wasBuilt() && $isRaw) {
            $this->entityBuilder->buildEntity($entity, true);
        } elseif (false === $entity->wasBuilt()) {
            $this->buildRefusingCycle($entity, $entityName);
        }

        if (false === $isRaw) {
            $this->entities[$entityName] = $entity;

            if ($entity->getTypicalEntityName() !== '') {
                $this->typicalEntities[$entity->getTypicalEntityName()] = $entity;
            }
        }

        return $entity;
    }

    /**
     * Builds $entity fully unless a full build of the same entity is already running: an entity is stored only after
     * its build, so a second full request from inside it would build another instance, and so on without end.
     *
     * @throws EntityDescriptorException when a full build of $entityName is already running
     */
    protected function buildRefusingCycle(EntityInterface & EntityDescriptorInterface $entity, string $entityName): void
    {
        if (\array_key_exists($entityName, $this->building)) {
            throw new EntityDescriptorException([
                'template'          => 'Entity {entity} is needed fully built while it is being built: '
                    . 'its roles form a cycle ({chain})',
                'entity'            => $entityName,
                'chain'             => \implode(' -> ', [...\array_keys($this->building), $entityName]),
            ]);
        }

        $this->building[$entityName] = true;

        try {
            $this->entityBuilder->buildEntity($entity);
        } finally {
            unset($this->building[$entityName]);
        }
    }

    /**
     * @throws EntityNotFound
     * @throws BaseException
     */
    #[\Override]
    public function getEntity(string $entityName, bool $isRaw = false): EntityInterface
    {
        return $this->getEntityDescriptor($entityName, $isRaw);
    }

    /**
     * @throws BaseException
     * @throws EntityNotFound
     */
    #[\Override]
    public function findEntity(string $entityName, bool $isRaw = false): ?EntityInterface
    {
        if (\array_key_exists($entityName, $this->entities)) {
            return $this->entities[$entityName];
        }

        if ($this->findEntityClass($entityName) === null) {
            return null;
        }

        return $this->getEntityDescriptor($entityName, $isRaw);
    }

    #[\Override]
    public function findTypicalEntity(string $entityName, bool $isRaw = false): ?EntityInterface
    {
        if (\array_key_exists($entityName, $this->typicalEntities)) {
            return $this->typicalEntities[$entityName];
        }

        return null;
    }

    #[\Override]
    public function setEntity(EntityInterface & EntityDescriptorInterface $entity): static
    {
        if (false === $entity->wasBuilt()) {

            if ($entity instanceof AutoResolverInterface) {
                $entity->resolveDependencies($this->container);
            }

            $this->entityBuilder->buildEntity($entity);
        }

        $this->entities[\ucfirst($entity->getEntityName())] = $entity;

        if ($entity->getTypicalEntityName() !== '') {
            $this->typicalEntities[\ucfirst($entity->getTypicalEntityName())] = $entity;
        }

        return $this;
    }

    /**
     * @throws UnexpectedValueType
     */
    #[\Override]
    public function newEntity(string $entityName): EntityInterface & EntityDescriptorInterface
    {
        $className                  = $this->blankEntityClass ?? BlankEntity::class;

        if (false === \class_exists($className)) {
            throw new UnexpectedValueType($className, 'class');
        }

        $entity                     = new $className();

        if ($entity instanceof EntityInterface === false) {
            throw new UnexpectedValueType($entity, EntityInterface::class);
        }

        if ($entity instanceof AutoResolverInterface) {
            $entity->resolveDependencies($this->container);
        }

        return $entity;
    }

    /**
     *
     * @throws  BaseException
     * @throws  EntityNotFound
     */
    protected function loadEntity(string $entityName, bool $isRaw = false): EntityInterface & EntityDescriptorInterface
    {
        $class                      = $this->findEntityClass($entityName);

        if ($class === null) {
            throw new EntityNotFound($entityName);
        }

        $entity                     = new $class($this, $isRaw);

        if ($entity instanceof AutoResolverInterface) {
            $entity->resolveDependencies($this->container);
        }

        return $entity;
    }

    #[\Override]
    public function findEntityClass(string $entityName): ?string
    {
        if ($this->entityNamespaces === []) {
            return null;
        }

        $entityName                 = \ucfirst($entityName);

        foreach ($this->entityNamespaces as $entityNamespace) {

            $class                  = $entityNamespace . '\\' . $entityName;

            if (\class_exists($class)) {
                return $class;
            }
        }

        return null;
    }
}
