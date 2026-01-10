<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Node\NodeInterface;

interface IndexDefinitionInterface extends NodeInterface
{
    final public const string INDEX_NAME    = 'indexName';

    final public const string INDEX_TYPE    = 'indexType';

    final public const string INDEX_ROLE    = 'indexRole';

    final public const string INDEX_PARTS   = 'indexParts';

    public function getIndexName(): ?string;

    public function getIndexType(): ?string;

    public function getIndexRole(): ?string;

    public function setIndexRole(string $indexRole): static;

    public function getIndexParts(): array;
}
