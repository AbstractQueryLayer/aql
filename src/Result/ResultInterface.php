<?php

declare(strict_types=1);

namespace IfCastle\AQL\Result;

interface ResultInterface
{
    /**
     * Brings the result to the final form.
     *
     * @return $this
     */
    public function finalize(): static;
}
