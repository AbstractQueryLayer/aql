<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor\Plan;

/**
 * Chain of Responsibility pattern for Result Processing.
 */
interface ResultProcessingPlanInterface
{
    /**
     * At this stage of processing, you can read the result of individual SQL queries as they are without additional processing.
     */
    final public const string RAW_READER = '=rr';

    /**
     * At this stage of processing, you can change the result columns for each query.
     */
    final public const string ROW_MODIFIER = '=m';

    /**
     * At this stage of processing, you can read the result of individual queries after the columns have been processed.
     */
    final public const string RESULT_READER = '=r';

    /**
     * Allows you to read the results of a query immediately after it is executed.
     *
     *
     * @return $this
     */
    public function addResultRawReader(ResultHandlerInterface $handler): static;

    /**
     * Processes query results row by row, called after addColumnModifier handlers.
     *
     * @return  $this
     */
    public function addRowModifier(RowModifierInterface $handler): static;

    /**
     * Allows reading query results after addColumnModifier and addRowModifier handlers.
     *
     * @return  $this
     */
    public function addResultReader(ResultReaderInterface $reader): static;
}
