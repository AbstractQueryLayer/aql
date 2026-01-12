<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Transaction\TransactionInterface;

class AdditionalOptions implements AdditionalOptionsInterface
{
    public function __construct(private array $options, private TransactionInterface|null $transaction = null) {}

    #[\Override]
    public function getTransaction(): ?TransactionInterface
    {
        return $this->transaction;
    }

    #[\Override]
    public function withTransaction(?TransactionInterface $transaction = null): static
    {
        if ($transaction === null) {
            return $this;
        }

        $this->transaction = $transaction;
        return $this;
    }

    #[\Override]
    public function getAdditionalOptions(): array
    {
        return $this->options;
    }

    #[\Override]
    public function setOption(string $name, mixed $value): void
    {
        $this->options[$name] = $value;
    }

    #[\Override]
    public function getOption(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }
}
