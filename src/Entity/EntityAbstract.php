<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity;

use IfCastle\AOP\AspectDescriptorInterface;
use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Entity\Builder\NamingStrategy\NamingStrategyInterface;
use IfCastle\AQL\Entity\Constraints\ConstraintInterface;
use IfCastle\AQL\Entity\DerivedEntity\DerivedEntityInterface;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorFailed;
use IfCastle\AQL\Entity\Functions\FunctionInterface;
use IfCastle\AQL\Entity\Key\KeyInterface;
use IfCastle\AQL\Entity\Modifiers\ModifierInterface;
use IfCastle\AQL\Entity\PostAction\PostActionDescriptorInterface;
use IfCastle\AQL\Entity\PostAction\PostActionEntityDescriptor;
use IfCastle\AQL\Entity\Property\PropertyInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\Exceptions\PropertyNotFound;
use IfCastle\AQL\Executor\FunctionHandlerInterface;
use IfCastle\AQL\Executor\QueryExecutorInterface;
use IfCastle\AQL\Executor\QueryExecutorResolverInterface;
use IfCastle\AQL\Executor\QueryHandlerInterface;
use IfCastle\AQL\Executor\QueryPostHandlerInterface;
use IfCastle\DesignPatterns\ExecutionPlan\ExecutionPlanInterface;
use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ConfigInterface;
use IfCastle\DI\ConfigMutable;
use IfCastle\DI\ConfigMutableInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\Exceptions\BaseException;
use IfCastle\Exceptions\UnexpectedValue;

/**
 * Base abstract class for describing an entity.
 */
abstract class EntityAbstract implements
    EntityInterface,
    AutoResolverInterface,
    QueryHandlerInterface,
    QueryPostHandlerInterface,
    FunctionHandlerInterface,
    EntityDescriptorInterface,
    QueryExecutorResolverInterface
{
    use EntityDependenciesTrait;
    #[\Override]
    public static function entity(): string
    {
        return \substr(\strrchr(static::class, '\\'), 1);
    }

    public static function namespace(): string
    {
        return \substr(static::class, 0, \strrpos(static::class, '\\'));
    }

    public static function getBaseDir(): string
    {
        return __DIR__;
    }

    protected string $name          = '';

    protected string $typicalName   = '';

    protected string $description   = '';

    /**
     * @var AspectDescriptorInterface[]|null
     */
    protected ?array $aspects       = null;

    /**
     * Name of database engine.
     */
    protected ?string $storageName  = null;

    protected ConfigMutableInterface $options;

    /**
     * Name of table in the DataBase.
     */
    protected string $dbTable       = '';

    /**
     * Name of entity from inherited this one.
     */
    protected ?string $inheritedEntity = null;

    /**
     * @var PropertyInterface[]
     */
    protected array $properties     = [];

    /**
     * @var PropertyInterface[]
     */
    protected array $typicalProperties = [];

    /**
     * @var KeyInterface[]
     */
    protected array $keys           = [];

    /**
     * @var FunctionInterface[]
     */
    protected array $functions      = [];

    /**
     * @var array<string, RelationInterface>
     */
    protected array $relations      = [];

    /**
     * @var ModifierInterface[]
     */
    protected array $modifiers      = [];

    /**
     * @var ConstraintInterface[]
     */
    protected array $constraints    = [];

    /**
     * Collection of queries executors
     * One action => one executor.
     * @var QueryExecutorInterface[]
     */
    protected array $actions        = [];

    /**
     * @var PostActionDescriptorInterface[]
     */
    protected array $postActions    = [];

    /**
     * Name of a primary key (id).
     */
    protected ?KeyInterface $primaryKey = null;

    /**
     * Is the primary key required?
     */
    protected bool $isPrimaryKeyRequired = true;

    /**
     * Name of title property.
     */
    protected string $titleProperty = '';

    /**
     * Point to build entity as raw.
     */
    protected bool $buildAsRaw      = false;

    /**
     * Stage of build entity.
     */
    protected int $buildStage       = 0;

    /**
     * @var QueryHandlerInterface[]
     */
    protected array $queryHandlers  = [];

    /**
     * @var QueryPostHandlerInterface[]
     */
    protected array $queryPostHandlers = [];

    private ?ExecutionPlanInterface $buildPlan = null;

    /**
     * Cache for ReflectionClass.
     */
    private ?\ReflectionClass $reflectionClass = null;

    /**
     * @throws BaseException
     */
    final public function __construct()
    {
        if ($this->buildStage === 0) {
            $this->buildPlan        = $this->defineBuildPlan();
        }
    }

    #[\Override]
    public function handleQuery(BasicQueryInterface $query, NodeContextInterface $context): void
    {
        if ($this->queryHandlers === [] || $query->getSubstitution() !== null) {
            return;
        }

        foreach ($this->queryHandlers as $handler) {
            $handler->handleQuery($query, $context);

            if ($query->getSubstitution() !== null) {
                break;
            }
        }
    }

    #[\Override]
    public function postHandleQuery(BasicQueryInterface $query, NodeContextInterface $context): void
    {
        if ($this->queryPostHandlers === [] || $query->getSubstitution() !== null) {
            return;
        }

        foreach ($this->queryPostHandlers as $handler) {
            $handler->postHandleQuery($query, $context);

            if ($query->getSubstitution() !== null) {
                break;
            }
        }
    }

    #[\Override]
    public function handleFunction(FunctionReferenceInterface $function, NodeContextInterface $context): void
    {
        if ($this->functions === []) {
            return;
        }

        foreach ($this->functions as $functionHandler) {
            if ($functionHandler->isCompatibleWith($function)) {

                $functionHandler->handleFunction($function, $context);

                if ($function->getSubstitution() !== null) {
                    return;
                }
            }
        }
    }

    #[\Override]
    public function getEntityAspects(): array
    {
        if ($this->aspects !== null) {
            return $this->aspects;
        }

        $this->aspects              = [];

        foreach ($this->attributesGenerator() as $descriptor) {
            $attribute              = $descriptor->newInstance();

            if ($attribute instanceof AspectDescriptorInterface
                && false === \array_key_exists($attribute->getAspectName(), $this->aspects)) {
                $this->aspects[$attribute->getAspectName()] = $attribute;
            }
        }

        return $this->aspects;
    }

    #[\Override]
    public function findEntityAspect(string $aspectName): ?AspectDescriptorInterface
    {
        return $this->getEntityAspects()[$aspectName] ?? null;
    }

    /**
     * Returns property by name.
     * Name can be string that started at '@' then function MUST return typical property.
     *
     *
     * @throws  PropertyNotFound
     */
    #[\Override]
    public function getProperty(string $propertyName, bool $isThrow = true): ?PropertyInterface
    {
        // If name starts at @ we use normalize properties
        if (\str_starts_with($propertyName, '@')) {

            $propertyName                   = \strtolower(\substr($propertyName, 1));

            if ($isThrow && !\array_key_exists($propertyName, $this->typicalProperties)) {
                throw new PropertyNotFound($this->getEntityName(), '@' . $propertyName);
            }

            return $this->typicalProperties[$propertyName] ?? null;
        }

        if ($isThrow && !\array_key_exists($propertyName, $this->properties)) {
            throw new PropertyNotFound($this->getEntityName(), $propertyName);
        }

        return $this->properties[$propertyName] ?? null;
    }

    /**
     *
     * @throws PropertyNotFound
     */
    #[\Override]
    public function getTypicalProperty(string $name, bool $isThrow = true): ?PropertyInterface
    {
        return $this->getProperty('@' . $name, $isThrow);
    }

    /**
     * Returns property name for Select.
     */
    #[\Override]
    public function getDefaultColumns(): array
    {
        $results                    = [];

        foreach ($this->properties as $property) {
            if ($property->isVirtual() === false) {
                $results[]          = $property->getName();
            }
        }

        return $results;
    }

    #[\Override]
    public function getInheritedEntity(): ?string
    {
        return $this->inheritedEntity;
    }

    #[\Override]
    public function getProperties(): array
    {
        return $this->properties;
    }

    #[\Override]
    public function getTypicalProperties(): array
    {
        return $this->typicalProperties;
    }

    #[\Override]
    public function findAutoIncrement(): ?PropertyInterface
    {
        foreach ($this->properties as $property) {
            if ($property->isAutoIncrement()) {
                return $property;
            }
        }

        return null;
    }

    #[\Override]
    public function getKeys(): array
    {
        return $this->keys;
    }

    #[\Override]
    public function getFunctions(): array
    {
        return $this->functions;
    }

    #[\Override]
    public function findFunction(FunctionReferenceInterface $functionReference): ?FunctionInterface
    {
        foreach ($this->functions as $function) {
            if ($function->isCompatibleWith($functionReference)) {
                return $function;
            }
        }

        return null;
    }

    /**
     * @return array<string, RelationInterface>
     */
    #[\Override]
    public function getRelations(): array
    {
        return $this->relations;
    }

    #[\Override]
    public function getModifiers(): array
    {
        return $this->modifiers;
    }

    #[\Override]
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    #[\Override]
    public function getActions(): array
    {
        return $this->actions;
    }

    #[\Override]
    public function getPrimaryKey(): ?KeyInterface
    {
        return $this->primaryKey;
    }

    #[\Override]
    public function getTitleProperty(): string
    {
        return $this->titleProperty;
    }

    #[\Override]
    public function getRelation(string $toEntity): RelationInterface
    {
        if (false === \array_key_exists($toEntity, $this->relations)) {
            throw new EntityDescriptorException([
                'template'          => 'Relation between entities {from} and {to} were not found',
                'from'              => $this->getEntityName(),
                'to'                => $toEntity,
            ]);
        }

        return $this->relations[$toEntity];
    }

    #[\Override]
    public function getEntityName(): string
    {
        return $this->name;
    }

    #[\Override]
    public function getTypicalEntityName(): string
    {
        return $this->typicalName;
    }

    /**
     * @throws EntityDescriptorException
     */
    #[\Override]
    public function setTypicalEntityName(string $typicalEntityName): static
    {
        $this->throwOutOfInitialization();
        $this->typicalName          = $typicalEntityName;
        return $this;
    }

    #[\Override]
    public function getSubject(): string
    {
        return $this->dbTable;
    }

    #[\Override]
    public function getStorageName(): ?string
    {
        return $this->storageName;
    }

    #[\Override]
    public function getOptions(): ConfigInterface
    {
        return $this->options;
    }

    #[\Override]
    public function getOptionsMutable(): ConfigMutableInterface
    {
        return $this->options;
    }

    #[\Override]
    public function resolveQueryExecutor(BasicQueryInterface $basicQuery, ?EntityInterface $entity = null): ?QueryExecutorInterface
    {
        return $this->actions[$basicQuery->getQueryAction()] ?? null;
    }

    #[\Override]
    public function findRelation(string $toEntity): ?RelationInterface
    {
        return $this->relations[$toEntity] ?? null;
    }

    /**
     * Resolve relation from $this to $toEntity.
     *
     *
     * @throws EntityDescriptorException
     */
    #[\Override]
    public function resolveRelation(EntityInterface $toEntity): RelationInterface
    {
        $relation                   = $this->findRelation($toEntity->getEntityName());

        if ($relation === null) {
            $relation               = $this->reverseRelation($toEntity->getRelation($this->getEntityName()));
        } else {
            $relation               = clone $relation;
        }

        if ($relation === null) {
            // "Unable to build relations between entities '{$this->getName()}' and '{$toEntity->getName()}'"
            throw new EntityDescriptorException([
                'template'          => 'Unable to build relation between entities {from}->{to}',
                'from'              => $this->getEntityName(),
                'to'                => $toEntity->getEntityName(),
            ]);
        }

        return $relation;
    }

    /**
     * Converts the relationship between Entity A and Entity B to a relationship between B and A.
     *
     */
    #[\Override]
    public function reverseRelation(?RelationInterface $relation = null): ?RelationInterface
    {
        return $relation?->reverseRelation();
    }

    #[\Override]
    public function isConsistentRelationWith(EntityInterface $withEntity): bool|null
    {
        if ($withEntity instanceof DerivedEntityInterface) {
            // dereference derived entity
            $withEntity            = $withEntity->getOriginalEntity();
        }

        if ($withEntity->getEntityName() === $this->getEntityName()) {
            return true;
        }

        $relation                   = $this->findRelation($withEntity->getEntityName())
                                      ?? $withEntity->findRelation($this->getEntityName());

        return $relation?->isConsistentRelations();
    }

    #[\Override]
    public function getBuildPlan(): ?EntityBuildPlanInterface
    {
        return $this->buildPlan;
    }

    /**
     * @throws UnexpectedValue
     */
    protected function defineBuildPlan(): EntityBuildPlanInterface
    {
        $buildPlan                  = new EntityBuildPlan();

        $buildPlan
            ->addStageHandler(EntityBuildPlanInterface::STEP_START, $this->buildStart(...))
            ->addStageHandler(EntityBuildPlanInterface::STEP_ASPECTS, $this->buildAspects(...))
            ->addStageHandler(EntityBuildPlanInterface::STEP_PROPERTIES, $this->buildProperties(...))
            ->addStageHandler(EntityBuildPlanInterface::STEP_INHERIT, $this->buildInheritedElements(...))
            ->addStageHandler(EntityBuildPlanInterface::STEP_AFTER_PROPERTIES, $this->buildAfterProperties(...))
            ->addStageHandler(EntityBuildPlanInterface::STEP_KEYS, $this->buildKeys(...))
            ->addStageHandler(EntityBuildPlanInterface::STEP_FUNCTIONS, $this->buildFunctions(...))
            ->addStageHandler(EntityBuildPlanInterface::STEP_MODIFIERS, $this->buildModifiers(...))
            ->addStageHandler(EntityBuildPlanInterface::STEP_RELATIONS, $this->buildRelations(...))
            ->addStageHandler(EntityBuildPlanInterface::STEP_AFTER_RELATIONS, $this->buildAfterRelations(...))
            ->addStageHandler(EntityBuildPlanInterface::STEP_CONSTRAINTS, $this->buildConstraints(...))
            ->addStageHandler(EntityBuildPlanInterface::STEP_ACTIONS, $this->buildActions(...))
            ->addStageHandler(EntityBuildPlanInterface::STEP_END, $this->buildEnd(...));

        return $buildPlan;
    }

    protected function beforeBuild(): void {}

    #[\Override]
    public function build(bool $isRaw = false): void
    {
        if ($this->buildStage !== 0) {
            return;
        }

        $this->buildAsRaw           = $isRaw;

        // start of build
        $this->buildStage           = 1;

        $this->beforeBuild();
        $this->buildPlan->executePlan();
        $this->buildPlan->dispose();
        $this->buildPlan            = null;

        // end of build
        $this->buildStage           = 2;

        $this->options->asImmutable();
    }

    #[\Override]
    public function wasBuilt(): bool
    {
        return $this->buildStage === 2;
    }

    /**
     * @return  $this
     * @throws  EntityDescriptorException
     */
    #[\Override]
    public function describeAspect(AspectDescriptorInterface $aspectDescriptor, bool $isRedefine = false): static
    {
        if ($this->aspects === null) {
            $this->aspects          = [];
        }

        $this->throwOutOfInitialization();
        $this->throwIfRedefine('aspect', $aspectDescriptor->getAspectName(), $this->aspects, $isRedefine);

        $this->aspects[$aspectDescriptor->getAspectName()] = $aspectDescriptor;

        return $this;
    }

    /**
     * @throws EntityDescriptorException
     */
    #[\Override]
    public function describeProperty(PropertyInterface $property, bool $isRedefine = false): static
    {
        $this->throwOutOfInitialization();

        $key                        = $property->getName();
        $this->throwIfRedefine('property', $key, $this->properties, $isRedefine);

        $this->properties[$key]     = $property;

        if ($property->getTypicalName() !== null) {
            $this->throwIfRedefine('typicalProperty', $property->getTypicalName(), $this->typicalProperties, $isRedefine);
            $this->typicalProperties[$property->getTypicalName()] = $property;
        }

        return $this;
    }

    #[\Override]
    public function describeReference(
        string|(EntityInterface & EntityDescriptorInterface) $toEntity,
        string                                               $relationType  = RelationInterface::REFERENCE,
        bool                                                 $isRequired    = true,
        ?string                                               $propertyName  = null
    ): static {
        if ($this->buildAsRaw) {
            return $this;
        }

        $this->getEntityBuilder()->buildReference($this, $toEntity, $relationType, $isRequired);
        return $this;
    }

    #[\Override]
    public function describeCrossReference(
        (EntityDescriptorInterface&EntityInterface)|string $toEntity,
        ?string                                             $throughEntity   = null,
        bool                                               $isRequired      = true,
        string                                             $relationType    = RelationInterface::REFERENCE): static
    {
        if ($this->buildAsRaw) {
            return $this;
        }

        $this->getEntityBuilder()->buildCrossReference($this, $toEntity, $isRequired, $throughEntity, $relationType);

        return $this;
    }

    /**
     * @return $this
     * @throws EntityDescriptorException
     */
    #[\Override]
    public function describeKey(KeyInterface $key, bool $isRedefine = false): static
    {
        $this->throwOutOfInitialization();

        foreach ($key->getKeyColumns() as $column) {
            if (false === \array_key_exists($column, $this->properties)) {
                throw new EntityDescriptorException([
                    'template'      => 'Key {key} try use property {column} that is not defined in an entity {entity}',
                    'key'           => $key,
                    'column'        => $column,
                    'entity'        => $this->getEntityName(),
                ]);
            }
        }

        $keyName                    = $key->getKeyName();
        $this->throwIfRedefine('key', $keyName, $this->keys, $isRedefine);
        $this->keys[$keyName]       = $key;

        if ($key->isPrimary()) {

            if (false === $isRedefine && $this->primaryKey !== null) {
                throw new EntityDescriptorException([
                    'template'      => 'Try to redefine primary key {key} for {entity}',
                    'key'           => $key,
                    'entity'        => $this->getEntityName(),
                ]);
            }

            $this->primaryKey       = $key;
        }

        return $this;
    }

    /**
     * @return  $this
     *
     * @throws EntityDescriptorException
     */
    #[\Override]
    public function describeFunction(FunctionInterface $function, bool $isRedefine = false): static
    {
        $this->throwOutOfInitialization();

        $key                        = $function->getFunctionName();
        $this->throwIfRedefine('function', $key, $this->functions, $isRedefine);

        $this->functions[$key]      = $function;

        return $this;
    }

    /**
     * @return  $this
     * @throws  EntityDescriptorException
     */
    #[\Override]
    public function describeModifier(ModifierInterface $modifier, bool $isRedefine = false): static
    {
        $this->throwOutOfInitialization();
        $this->modifiers[]          = $modifier;
        return $this;
    }

    /**
     * @return  $this
     * @throws  EntityDescriptorException
     */
    #[\Override]
    public function describeRelation(RelationInterface $relation, bool $isRedefine = false): static
    {
        $this->throwOutOfInitialization();

        $key                        = $relation->getRelationName();
        $this->throwIfRedefine('relation', $key, $this->relations, $isRedefine);

        $this->relations[$key]      = $relation;

        return $this;
    }

    /**
     *
     * @return  $this
     * @throws  EntityDescriptorException
     */
    #[\Override]
    public function describeConstraint(ConstraintInterface $constraint): static
    {
        $this->throwOutOfInitialization();
        $this->constraints[]        = $constraint;

        return $this;
    }

    /**
     * @throws EntityDescriptorException
     */
    #[\Override]
    public function addQueryHandler(QueryHandlerInterface $queryHandler): static
    {
        $this->throwOutOfInitialization();
        $this->queryHandlers[]      = $queryHandler;
        return $this;
    }

    /**
     * @throws EntityDescriptorException
     */
    #[\Override]
    public function addQueryPostHandler(QueryPostHandlerInterface $queryPostHandler): static
    {
        $this->throwOutOfInitialization();
        $this->queryPostHandlers[]   = $queryPostHandler;
        return $this;
    }

    /**
     *
     *
     * @return  $this
     *
     * @throws  EntityDescriptorException
     */
    #[\Override]
    public function describeAction(string $action, QueryExecutorInterface $queryExecutor, bool $isRedefine = false): static
    {
        $this->throwOutOfInitialization();
        $this->throwIfRedefine('action', $action, $this->actions, $isRedefine);

        $this->actions[$action]     = $queryExecutor;

        return $this;
    }

    #[\Override]
    public function getPostActions(): array
    {
        return $this->postActions;
    }

    #[\Override]
    public function describePostAction(
        callable|PostActionDescriptorInterface $callback,
        array|string|null                           $action = null
    ): void {
        if (false === $callback instanceof PostActionDescriptorInterface) {
            $callback               = new PostActionEntityDescriptor($action, $callback);
        }

        $this->postActions[]        = $callback;
    }

    #[\Override]
    public function getNamingStrategy(): NamingStrategyInterface
    {
        return $this->getDiContainer()->resolveDependency(NamingStrategyInterface::class);
    }

    /**
     * @throws EntityDescriptorException
     */
    protected function throwOutOfInitialization(): void
    {
        if ($this->buildStage !== 1) {
            throw new EntityDescriptorException([
                'template'          => 'Attempting to change an entity {entity} outside the initialization process',
                'entity'            => $this->getEntityName(),
            ]);
        }
    }

    /**
     *
     * @throws  EntityDescriptorException
     */
    protected function throwIfRedefine(string $what, string $key, array $list, bool $isRedefine): void
    {
        if (false === $isRedefine && \array_key_exists($key, $list)) {
            throw new EntityDescriptorException([
                'template'          => \sprintf('Try to redefine %s {item} for {entity}', $what),
                'item'              => $key,
                'entity'            => $this->getEntityName(),
            ]);
        }
    }

    /**
     * Returns the name of the entity from which it is inherited.
     */
    protected function defineInheritedEntity(): ?string
    {
        return null;
    }

    protected function buildStart(): void
    {
        // auto define entityName
        if ($this->name === '') {
            $this->name             = static::entity();
        }

        $this->inheritedEntity      = $this->defineInheritedEntity();

        // Auto generate table name by selfName
        if ($this->dbTable === '') {
            $this->dbTable          = $this->getNamingStrategy()->generateTableName($this);
        }

        if (empty($this->options)) {
            $this->options          = new ConfigMutable();
        }
    }

    abstract protected function buildAspects(): void;

    protected function buildInheritedElements(): void
    {
        if ($this->inheritedEntity === null) {
            return;
        }

        $this->getEntityBuilder()->buildInheritsEntity($this, $this->inheritedEntity);
    }

    abstract protected function buildProperties(): void;

    /**
     * @throws EntityDescriptorFailed
     */
    protected function buildAfterProperties(): void
    {
        if ($this->properties === []) {
            throw new EntityDescriptorFailed(static::entity(), 'The properties is empty');
        }

        /**
         * Able properties to change the structure of the entity
         * into which they enter.
         */
        foreach ($this->properties as $property) {
            $property->handleEntityDefinition($this, $this->getDiContainer());
        }

        if ($this->primaryKey === null && $this->isPrimaryKeyRequired) {
            throw new EntityDescriptorFailed(static::entity(), 'The entity should have a primary key');
        }
    }

    protected function buildKeys(): void {}

    protected function buildFunctions(): void {}

    protected function buildModifiers(): void {}

    protected function buildRelations(): void {}

    protected function buildAfterRelations(): void
    {
        foreach ($this->properties as $property) {
            $property->handleAfterRelationDefinition($this, $this->getDiContainer());
        }
    }

    protected function buildConstraints(): void {}

    protected function buildActions(): void
    {
        //
        // Build default action executors
        //
        $this->actions              = [
            QueryInterface::ACTION_SELECT  => null,
            QueryInterface::ACTION_COUNT   => null,
            QueryInterface::ACTION_INSERT  => null,
            QueryInterface::ACTION_REPLACE => null,
            QueryInterface::ACTION_UPDATE  => null,
            QueryInterface::ACTION_DELETE  => null,
        ];
    }

    protected function buildEnd(): void {}

    protected function getReflectionClass(): \ReflectionClass
    {
        if ($this->reflectionClass !== null) {
            return $this->reflectionClass;
        }

        $this->reflectionClass      = new \ReflectionClass($this);

        return $this->reflectionClass;
    }

    protected function attributesGenerator(): \Generator
    {
        $reflectionClass            = $this->getReflectionClass();

        while ($reflectionClass !== null && $reflectionClass !== false) {
            foreach ($reflectionClass->getAttributes() as $attribute) {
                yield $attribute;
            }

            $reflectionClass        = $reflectionClass->getParentClass();
        }
    }

    protected \WeakReference|null $diContainer = null;

    #[\Override]
    public function resolveDependencies(ContainerInterface $container): void
    {
        $this->diContainer         = \WeakReference::create($container);
    }

    #[\Override]
    protected function getDiContainer(): ContainerInterface
    {
        return $this->diContainer?->get();
    }
}
