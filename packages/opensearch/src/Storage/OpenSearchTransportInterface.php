<?php

declare(strict_types=1);

namespace IfCastle\AQL\OpenSearch\Storage;

use IfCastle\AQL\Storage\Exceptions\StorageException;

/**
 * The REST API of one OpenSearch node, as the application reaches it: the storage builds request bodies and reads
 * replies, the transport owns the connection, timeouts and error mapping.
 */
interface OpenSearchTransportInterface
{
    /**
     * Sends one JSON request and returns the decoded reply.
     *
     * @param string $path the request path with its query string, index names already encoded
     * @param ?array<mixed> $body the JSON body, or null for none
     * @return array<mixed>
     * @throws \Throwable the transport's own failure when the node cannot be reached or refuses the request
     */
    public function request(string $method, string $path, ?array $body = null): array;
}
