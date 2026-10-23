<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

/**
 * A storage that pools its own connections: one instance serves every coroutine, each on a connection of its own, and
 * keeps the transactions of a coroutine on that coroutine's connection. A storage pool lends one shared instance of
 * such a storage instead of pooling it again.
 */
interface SelfPoolingStorageInterface extends StorageInterface {}
