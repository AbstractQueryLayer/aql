<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Scope;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\QueryOptionInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\SubjectInterface;
use IfCastle\AQL\Executor\ColumnHandlerInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;
use IfCastle\AQL\Executor\FunctionHandlerInterface;
use IfCastle\AQL\Executor\OptionHandlerInterface;
use IfCastle\AQL\Executor\QueryHandlerInterface;
use IfCastle\AQL\Executor\SubjectHandlerInterface;
use IfCastle\DesignPatterns\ScopeControl\ScopeContextInterface;
use IfCastle\DesignPatterns\ScopeControl\ScopeInterface;
use IfCastle\DesignPatterns\ScopeControl\ScopeProcessorInterface;
use IfCastle\DI\AutoResolverInterface;
use IfCastle\DI\ContainerInterface;

class ScopeProcessor implements
    ScopeProcessorInterface,
    QueryHandlerInterface,
    ColumnHandlerInterface,
    FunctionHandlerInterface,
    OptionHandlerInterface,
    SubjectHandlerInterface,
    AutoResolverInterface
{
    public static function defaultScopeProcessorByAspect(): ScopeProcessorInterface
    {
        return new self([
            ScopeInterface::SCOPE_DEFAULT       => [
                ScopeInterface::SCOPE_DEFAULT   => new EntityAccessByScopeRule(ScopeInterface::SCOPE_DEFAULT),
            ],
            ScopeInterface::SCOPE_PUBLIC        => [
                ScopeInterface::SCOPE_DEFAULT   => new EntityAccessByScopeRule(ScopeInterface::SCOPE_PUBLIC),
            ],
            ScopeInterface::SCOPE_ADMIN         => [
                ScopeInterface::SCOPE_DEFAULT   => new EntityAccessByScopeRule(ScopeInterface::SCOPE_ADMIN),
            ],
            ScopeInterface::SCOPE_ROOT          => [
                ScopeInterface::SCOPE_DEFAULT   => new EntityAccessByScopeRule(ScopeInterface::SCOPE_ROOT),
            ],
        ]);
    }

    protected ScopeContextInterface $scopeContext;

    public function __construct(
        /**
         * @var array <string, array<string, EntityScopeRulesI>>
         */
        protected array $scopes = []
    ) {}

    #[\Override]
    public function resolveDependencies(ContainerInterface $container): void
    {
        $this->scopeContext         = $container->resolveDependency(ScopeContextInterface::class);
    }


    #[\Override]
    public function handleQuery(BasicQueryInterface $query, NodeContextInterface $context): void
    {
        $this->defineEntityRules($query, $query->getMainEntityName())?->handleQuery($query, $context);
    }

    #[\Override]
    public function handleColumn(ColumnInterface $column, NodeContextInterface $context): void
    {
        $this->defineEntityRules($context->getBasicQuery(), $column->getEntityName() ?? $context->getCurrentEntity()->getEntityName())
            ?->handleColumn($column, $context);
    }

    #[\Override]
    public function handleFunction(FunctionReferenceInterface $function, NodeContextInterface $context): void
    {
        $this->defineEntityRules($context->getBasicQuery(), $function->getEntityName() ?? $context->getCurrentEntity()->getEntityName())
            ?->handleFunction($function, $context);
    }

    #[\Override]
    public function handleOption(QueryOptionInterface $queryOption, NodeContextInterface $context): void
    {
        $this->defineEntityRules($context->getBasicQuery(), $context->getMainEntity()->getEntityName())
            ?->handleOption($queryOption, $context);
    }

    #[\Override]
    public function handleSubject(SubjectInterface $subject, NodeContextInterface $context): void
    {
        $this->defineEntityRules($context->getBasicQuery(), $subject->getSubjectName())
            ?->handleSubject($subject, $context);
    }

    #[\Override]
    public function handleJoin(JoinInterface $join, NodeContextInterface $context): void
    {
        $this->defineEntityRules($context->getBasicQuery(), $join->getSubject()->getSubjectName())
            ?->handleJoin($join, $context);
    }

    protected function defineEntityRules(ScopeInterface $scope, string $entityName): ?EntityScopeRulesInterface
    {
        $scope                      = match ($scope->getScopeName()) {
            ScopeInterface::SCOPE_DEFAULT => $this->scopeContext->getScopeName(),
            default                 => $scope->getScopeName()
        };

        $scopeRules                 = $this->scopes[$scope] ?? $this->scopes[ScopeInterface::SCOPE_DEFAULT] ?? [];
        return $scopeRules[$entityName] ?? $scopeRules[ScopeInterface::SCOPE_DEFAULT] ?? null;
    }
}
