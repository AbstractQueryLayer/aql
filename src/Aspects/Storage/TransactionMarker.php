<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\Storage;

use Attribute;

/**
 * Defines the UUID field to associate a transaction with a record in the database.
 * This is used for the logical transaction mechanism.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class TransactionMarker
{
    public const string TRANSACTION = '_transaction';
}
