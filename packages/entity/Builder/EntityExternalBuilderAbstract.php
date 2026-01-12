<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Builder;

use IfCastle\AQL\Entity\EntityBuildPlanInterface;
use IfCastle\AQL\Entity\EntityDependenciesTrait;
use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\DI\ContainerInterface;

/**
 * class is used to create entity from outside.
 */
abstract class EntityExternalBuilderAbstract
{
    use EntityDependenciesTrait;

    public function __construct(
        protected EntityInterface & EntityDescriptorInterface $entity,
        protected ContainerInterface $diContainer
    ) {
        $this->buildAdditionalEntities();
        $this->beforeBuild();

        $entity->getBuildPlan()
            ->addAfterActionHandler(EntityBuildPlanInterface::STEP_ASPECTS, $this->buildAspects(...))
            ->addBeforeActionHandler(EntityBuildPlanInterface::STEP_PROPERTIES, $this->buildPropertiesBefore(...));
    }

    #[\Override]
    protected function getDiContainer(): ContainerInterface
    {
        return $this->diContainer;
    }

    protected function ifEntityNotExists(string $entityName): bool
    {
        return $this->getEntityStorage()->findEntity($entityName) === null;
    }

    protected function ifNotTypicalEntityExists(string $entityName): bool
    {
        return $this->getEntityStorage()->findTypicalEntity($entityName) !== null;
    }

    protected function beforeBuild(): void {}

    protected function describeEntity(EntityInterface $entity): void
    {
        $this->getEntityStorage()->setEntity($entity);
    }

    protected function buildAdditionalEntities(): void {}

    /**
     * @throws EntityDescriptorException
     */
    protected function buildAspects(): void {}

    /**
     * @throws EntityDescriptorException
     */
    protected function buildPropertiesBefore(): void {}

    protected function resolveTypicalEntityName(string $entityName): string
    {
        return $this->getEntityStorage()->findTypicalEntity($entityName)?->getEntityName() ?? $entityName;
    }
}
