<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\Sql\Query\Expression\JoinInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\SubjectInterface;
use IfCastle\AQL\Executor\Context\NodeContextInterface;

interface SubjectHandlerInterface
{
    /**
     * Handles a reference to a Database Entity.
     *
     *
     */
    public function handleSubject(SubjectInterface $subject, NodeContextInterface $context): void;

    /**
     * Handles an entity occurrence in a query.
     *
     *
     */
    public function handleJoin(JoinInterface $join, NodeContextInterface $context): void;
}
