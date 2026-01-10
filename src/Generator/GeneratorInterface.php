<?php

declare(strict_types=1);

namespace IfCastle\AQL\Generator;

/**
 * ## Generator interface
 * A very simple generator interface that should generate something.
 *
 */
interface GeneratorInterface
{
    public function generate(): void;
}
