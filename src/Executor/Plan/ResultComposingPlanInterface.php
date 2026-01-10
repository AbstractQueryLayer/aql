<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

interface ResultComposingPlanInterface
{
    /**
     * At this stage of processing, the results of different queries are combined into a single result.
     *
     * @var string
     */
    final public const string RESULT_COMPOSER = '=c';

    /**
     * At this stage of processing, you can read the final result of the entire query.
     *
     * @var string
     */
    final public const string POST_READER = '=pr';

    /**
     * Processes the results of the query with the ability to change its structure and type.
     *
     *
     * @return $this
     */
    public function addResultComposer(ResultHandlerInterface $handler): static;

    /**
     * Allows you to read the final result of a query.
     *
     *
     * @return  $this
     */
    public function addResultPostReader(ResultReaderInterface $reader): static;
}
