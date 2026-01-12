<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Node;

use IfCastle\DesignPatterns\Iterators\RecursiveIteratorByIteratorWithPath;

/**
 * This iterator recursively traverses Nodes in order from Parent to Child.
 * The iterator follows the rules of the normalization process:
 *
 * 1. Normalized nodes are skipped.
 * 2. If a node has a substitute, the substitute is used instead of the original node.
 *
 * Additionally, the class also creates an execution context for each node using a specific algorithm.
 * The execution context helps the Executor code process nodes efficiently.
 *
 * Each node receives its execution context, which is either inherited from basicContext or is equal to it.
 * A child node receives a context that refers to the parent node.
 * For example, a Tuple node gets the query context, and so on.
 *
 * An exception is nodes of type JOIN;
 * they always create their own special context.
 */
class RecursiveIteratorByNodeIterator extends RecursiveIteratorByIteratorWithPath implements RecursiveIteratorByNodeIteratorInterface
{
    public function __construct(NodeTransformerIteratorInterface $currentIterator)
    {
        parent::__construct($currentIterator);
    }

    #[\Override]
    public function getCurrentTransformerIterator(): NodeTransformerIteratorInterface|null
    {
        if ($this->currentIterator instanceof NodeTransformerIteratorInterface) {
            return $this->currentIterator;
        }

        return null;
    }
}
