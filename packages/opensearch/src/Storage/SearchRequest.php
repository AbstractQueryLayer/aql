<?php

declare(strict_types=1);

namespace IfCastle\AQL\OpenSearch\Storage;

/** One rendered `_search` request and how its hits become rows. */
final readonly class SearchRequest
{
    /**
     * @param string $index the index name, not encoded
     * @param array<string, mixed> $body the request body
     * @param array<string, string> $columns the row key of each source field, in tuple order
     * @param bool $everyRow true when every match is read page by page with `search_after` on the body's sort
     */
    public function __construct(public string $index, public array $body, public array $columns,
        public bool $everyRow) {}
}
