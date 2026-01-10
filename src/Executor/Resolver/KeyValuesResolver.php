<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Resolver;

use IfCastle\AQL\Dsl\Sql\Query\Expression\ValueListInterface;
use IfCastle\AQL\Dsl\Sql\Tuple\TupleColumnInterface;
use IfCastle\AQL\Executor\Plan\ResultReaderInterface;
use IfCastle\AQL\Result\ResultInterface;
use IfCastle\AQL\Result\TupleInterface;
use IfCastle\DI\DisposableInterface;
use IfCastle\Exceptions\UnexpectedValueType;

final class KeyValuesResolver implements ResultReaderInterface, DisposableInterface
{
    /**
     * @var string[]
     */
    private array $fromAliases;

    public function __construct(private ?ValueListInterface $valueList, TupleColumnInterface ... $fromEntityKeyColumns)
    {
        foreach ($fromEntityKeyColumns as $fromEntityKeyColumn) {
            $this->fromAliases[] = $fromEntityKeyColumn->getAliasOrColumnName();
        }
    }

    /**
     * @throws UnexpectedValueType
     */
    #[\Override]
    public function readResult(ResultInterface $result): void
    {
        if ($result instanceof TupleInterface === false) {
            throw new UnexpectedValueType('$result', $result, TupleInterface::class);
        }

        $this->valueList->defineValues($result->selectColumnsWithoutKeys(...$this->fromAliases));
    }

    #[\Override]
    public function __invoke(...$args): mixed
    {
        $this->readResult(...$args);
        return null;
    }

    #[\Override]
    public function dispose(): void
    {
        $this->valueList            = null;
    }
}
