<?php

declare(strict_types=1);

namespace IfCastle\AQL\Result;

interface ResultInterface
{
    /** Number of rows affected by the storage operation, before result decoration. */
    public function affected(): int;

    /**
     * Brings the result to the final form.
     *
     * @return $this
     */
    public function finalize(): static;
}
