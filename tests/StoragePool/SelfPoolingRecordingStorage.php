<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool;

use IfCastle\AQL\Storage\SelfPoolingStorageInterface;
use IfCastle\AQL\StoragePool\ReadWritePool\RecordingSqlStorage;

/** A recording storage that declares a connection pool of its own, as MySql does. */
final class SelfPoolingRecordingStorage extends RecordingSqlStorage implements SelfPoolingStorageInterface {}
