<?php

declare(strict_types=1);

namespace IfCastle\Jobs;

/** A job a worker holds: valid while its lease token is the one on the jobs row and the lease has not expired. */
final readonly class LeasedJob
{
    /**
     * @param array<string, mixed> $payload the decoded JSON payload
     * @param int $attempt this attempt's number, from 1
     * @param string $token the lease token, 16 raw bytes; renew(), complete() and fail() refuse any other
     */
    public function __construct(
        public int $id,
        public string $kind,
        public array $payload,
        public int $attempt,
        public string $token,
    ) {}
}
