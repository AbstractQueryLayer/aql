<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Transaction\TransactionInterface;

final class OptionsWithAdditionalHandler extends AdditionalOptions implements AdditionalHandlerAwareInterface, AdditionalOptionsInterface
{
    public function __construct(array $options, private readonly mixed $handler, TransactionInterface|null $transaction = null)
    {
        parent::__construct($options, $transaction);
    }

    #[\Override]
    public function getAdditionalHandler(): mixed
    {
        return $this->handler;
    }
}
