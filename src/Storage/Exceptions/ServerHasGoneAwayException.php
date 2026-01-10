<?php

declare(strict_types=1);

namespace IfCastle\AQL\Storage\Exceptions;

/**
 * The connection to the server was lost.
 */
class ServerHasGoneAwayException extends RecoverableException {}
