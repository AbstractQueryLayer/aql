<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\Ownership;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class DeletedBy {}
