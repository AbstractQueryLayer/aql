<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Populator;

use IfCastle\AQL\Dsl\Sql\Query\Insert;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Relation\DirectRelationInterface;
use IfCastle\AQL\Entity\Relation\IndirectRelationInterface;
use IfCastle\AQL\Executor\AqlExecutorInterface;
use IfCastle\AQL\Executor\Ddl\DdlStrategy;
use IfCastle\AQL\Result\InsertUpdateResultInterface;
use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ContainerInterface;
use IfCastle\Exceptions\RuntimeException;
use IfCastle\Exceptions\UnexpectedValueType;

class Populator implements PopulatorInterface, AutoResolverInterface
{
    protected ContainerInterface   $diContainer;

    protected AqlExecutorInterface $aqlExecutor;

    protected bool $isPopulated     = false;

    protected array $templateData   = [];

    protected array $populatedData  = [];

    public function __construct(protected EntityInterface $entity, protected bool $isRemoveExisted = false) {}

    #[\Override]
    public function resolveDependencies(ContainerInterface $container): void
    {
        $this->diContainer          = $container;
        $this->aqlExecutor          = $container->resolveDependency(AqlExecutorInterface::class);
    }

    #[\Override]
    public function isRemoveExisted(): bool
    {
        return $this->isRemoveExisted;
    }

    #[\Override]
    public function asRemoveExisted(): static
    {
        $this->isRemoveExisted      = true;

        return $this;
    }

    #[\Override]
    public function getPopulatorName(): string
    {
        return $this->entity->getEntityName();
    }

    #[\Override]
    public function getPopulatorEntity(): EntityInterface
    {
        return $this->entity;
    }

    #[\Override]
    public function populate(): void
    {
        if ($this->isPopulated) {
            return;
        }

        $this->isPopulated          = true;

        $this->populateDdl();

        foreach ($this->handleTemplateData() as $row) {
            $result                 = $this->aqlExecutor->executeAql(Insert::entity($this->entity->getEntityName())->assignKeyValues($row));

            if ($result instanceof InsertUpdateResultInterface) {
                $lastRow            = $result->getLastRow();

                if ($lastRow !== null) {
                    $this->populatedData[]  = $lastRow;
                }
            }
        }
    }

    /**
     * @throws UnexpectedValueType
     * @throws RuntimeException
     */
    protected function populateDdl(): void
    {
        $ddlStrategy                = DdlStrategy::instantiate($this->diContainer);

        if ($this->isRemoveExisted) {
            $ddlStrategy->asRemoveExisted();
        }

        $ddlStrategy->defineEntity($this->entity->getEntityName());
    }

    #[\Override]
    public function isPopulated(): bool
    {
        return $this->isPopulated;
    }

    #[\Override]
    public function getPopulatedData(): array
    {
        return $this->populatedData;
    }

    #[\Override]
    public function findPopulatedRowById(float|int|string|null $id = null): ?array
    {
        $primaryKey                 = $this->entity->getPrimaryKey()->getKeyName();

        foreach ($this->populatedData as $row) {
            if (\is_array($row) && \array_key_exists($primaryKey, $row) && $row[$primaryKey] === $id) {
                return $row;
            }
        }

        return null;
    }

    #[\Override]
    public function findPopulatedRowBy(string $column, mixed $value): ?array
    {
        foreach ($this->populatedData as $row) {
            if (\is_array($row) && \array_key_exists($column, $row) && $row[$column] === $value) {
                return $row;
            }
        }

        return null;
    }

    #[\Override]
    public function getPopulatedFirstId(): string|int|float|null
    {
        $primaryKey                 = $this->entity->getPrimaryKey()?->getKeyName();

        if ($primaryKey === null) {
            return null;
        }

        foreach ($this->populatedData as $row) {
            if (\is_array($row) && \array_key_exists($primaryKey, $row)) {
                return $row[$primaryKey];
            }
        }

        return null;
    }

    #[\Override]
    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    #[\Override]
    public function setTemplateData(array $rows): static
    {
        $this->templateData         = $rows;

        return $this;
    }

    #[\Override]
    public function addTemplateRow(array $row): static
    {
        $this->templateData[]       = $row;

        return $this;
    }

    /**
     * @throws RuntimeException
     */
    #[\Override]
    public function getReferenceKey(string $toEntityName): ?string
    {
        $relation                   = $this->entity->findRelation($toEntityName);

        if ($relation === null) {
            return null;
        }

        if ($relation instanceof DirectRelationInterface) {
            return $relation->getLeftKey()->getKeyName();
        }

        if ($relation instanceof IndirectRelationInterface) {
            throw new RuntimeException('IndirectRelation relation is not supported for reference');
        }

        throw new RuntimeException('Unknown relation type for reference');
    }

    protected function handleTemplateData(): array
    {
        return $this->templateData;
    }
}
