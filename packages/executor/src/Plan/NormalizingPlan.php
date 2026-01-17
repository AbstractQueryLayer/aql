<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\Exceptions\UnexpectedValueType;

class NormalizingPlan extends ExecutionPlan implements NormalizingPlanInterface
{
    /**
     * List of QueryCommandI,
     * which stored association between a target-query and normalizing query.
     *
     * @var QueryCommandInterface[]
     */
    protected array $normalizingCommands;

    protected NormalizingStrategyInterface $normalizingStrategy;

    #[\Override]
    public function defineNormalizingQuery(BasicQueryInterface $query): BasicQueryInterface
    {
        return $this->defineNormalizingCommand($query)->getContainedQuery();
    }

    #[\Override]
    public function defineNormalizingCommand(BasicQueryInterface $query): QueryCommandInterface
    {
        $hash                       = (string) \spl_object_id($query);

        if (\array_key_exists($hash, $this->normalizingCommands)) {
            return $this->normalizingCommands[$hash];
        }

        $command                    = $this->normalizingStrategy->buildNormalizingCommand($query, $this);

        $this->normalizingCommands[$hash] = $command;

        return $command;
    }

    #[\Override]
    public function defineNormalizingSqlCommand(QueryInterface $query): SqlQueryCommandInterface
    {
        $command                    = $this->defineNormalizingCommand($query);

        if ($command instanceof SqlQueryCommandInterface) {
            return $command;
        }

        throw new UnexpectedValueType('$command', $command, SqlQueryCommandInterface::class);
    }
}
