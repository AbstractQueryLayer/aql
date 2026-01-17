<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Relation;

enum RelationDirection: int
{
    /**
     * Read like: From Right To Left
     * Means A depends on B (A <== B)
     * first define B
     * then define A.
     * @var int
     */
    case FROM_RIGHT                 = -1;

    /**
     * Means A <=> B.
     *
     * @var int
     */
    case TWO_SIDED                  = 0;

    /**
     * Read like: From Left To Right
     * Means: B depends on A (A ==> B).
     * @var int
     */
    case FROM_LEFT                  = 1;
}
