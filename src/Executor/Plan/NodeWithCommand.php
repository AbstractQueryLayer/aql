<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

use IfCastle\AQL\Dsl\Node\NodeAbstract;

final class NodeWithCommand extends NodeAbstract implements CommandAwareInterface
{
    protected string $nodeName      = 'COMMAND';

    protected bool   $isTransformed = true;

    public function __construct(private readonly CommandInterface $command)
    {
        parent::__construct();
    }

    #[\Override]
    public function getCommand(): CommandInterface
    {
        return $this->command;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        if ($this->command instanceof QueryCommandInterface) {
            return '{query: ' . $this->command->getContainedQuery()->getAql($forResolved) . '}';
        }

        return '{command: ' . $this->command::class . ', for stage: ' . $this->command->getCommandStage() . '}';
    }
}
