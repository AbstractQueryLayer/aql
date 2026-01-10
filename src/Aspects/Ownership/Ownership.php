<?php

declare(strict_types=1);

namespace IfCastle\AQL\Aspects\Ownership;

use Attribute;

/**
 * The "Ownership" class represents an attribute of an object that defines its ownership.
 * This attribute allows managing user rights to the object,
 * which could be, for example, a forum post.
 * The attribute contains information about the user who owns the object,
 * enabling proper access control and management of the object's properties and actions based on ownership.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Ownership {}
