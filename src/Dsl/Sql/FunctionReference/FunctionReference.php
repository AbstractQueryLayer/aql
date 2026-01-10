<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\FunctionReference;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\AQL\Dsl\Node\NodeInterface;

class FunctionReference extends NodeAbstract implements FunctionReferenceInterface
{
    public static function real(string $functionName, NodeInterface ...$parameters): static
    {
        return new self($functionName, ...$parameters);
    }

    public static function virtual(string $functionName, NodeInterface ...$parameters): static
    {
        return (new self($functionName, ...$parameters))->asVirtual();
    }

    public static function global(string $functionName, NodeInterface ...$parameters): static
    {
        return (new self($functionName, ...$parameters))->asVirtual()->asGlobal();
    }

    protected ?string $entityName   = null;

    protected bool $isPure          = false;

    protected bool $isVirtual       = false;

    protected bool $isGlobal        = false;

    private bool $isResolved        = false;

    public function __construct(protected string $functionName, NodeInterface ...$parameters)
    {
        parent::__construct();
        $this->childNodes           = $parameters;

        foreach ($parameters as $parameter) {
            $parameter->setParentNode($this);
        }
    }

    #[\Override]
    public function getFunctionName(): string
    {
        return $this->functionName;
    }

    #[\Override]
    public function getEntityName(): ?string
    {
        return $this->entityName;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function setEntityName(string $entityName): static
    {
        $this->entityName           = $entityName;
        return $this;
    }

    #[\Override]
    public function getFunctionParameters(): array
    {
        return $this->childNodes;
    }

    #[\Override]
    public function isFunctionPure(): bool
    {
        return $this->isPure;
    }

    #[\Override]
    public function isVirtual(): bool
    {
        return $this->isVirtual;
    }

    #[\Override]
    public function isNotVirtual(): bool
    {
        return !$this->isVirtual;
    }

    #[\Override]
    public function isGlobal(): bool
    {
        return $this->isGlobal;
    }

    #[\Override]
    public function asVirtual(): static
    {
        $this->isVirtual            = true;
        return $this;
    }

    #[\Override]
    public function asGlobal(): static
    {
        $this->isGlobal             = true;
        return $this;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        $name                       = $this->functionName;

        if ($this->entityName !== null) {
            $name                   = $this->entityName . '.' . $name;
        } elseif ($this->isVirtual) {
            $name                   = '@' . $name;
        }

        return $name . '(' . $this->childNodesToAql(', ') . ')';
    }

    #[\Override]
    public function isEqual(FunctionReferenceInterface $functionReference): bool
    {
        return $this->isGlobal === $functionReference->isGlobal()
            && $this->isVirtual === $functionReference->isVirtual()
            && $this->functionName === $functionReference->getFunctionName();
    }

    #[\Override]
    protected function generateResult(): mixed
    {
        return $this->functionName . '(' . \implode(', ', $this->generateResultForChildNodes()) . ')';
    }

    #[\Override]
    public function isResolved(): bool
    {
        return $this->isResolved;
    }

    #[\Override]
    public function cloneAsResolved(): static
    {
        $clone                      = clone $this;
        $clone->isResolved          = true;
        return $clone;
    }

    #[\Override]
    public function resolveSelf(): void
    {
        $this->setSubstitution($this->cloneAsResolved());
    }
}
