<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage\Exceptions;

use IfCastle\Exceptions\BaseException;

class StorageException extends BaseException
{
    protected array $tags           = ['database'];
}
