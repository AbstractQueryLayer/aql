<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage;

use IfCastle\AQL\Dsl\BasicQueryInterface;
use IfCastle\AQL\Result\ResultInterface;

interface AqlStorageInterface extends StorageInterface
{
    public function executeAql(BasicQueryInterface $query, ?object $context = null): ResultInterface;
}
