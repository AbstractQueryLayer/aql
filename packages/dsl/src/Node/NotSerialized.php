<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

/**
 * Marks a node property that toArray() leaves out: state that belongs to a run (context, result, links to other
 * nodes) or that the node rebuilds on demand. A restored node holds the property's default value.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class NotSerialized {}
