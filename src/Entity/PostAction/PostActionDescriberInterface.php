<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\PostAction;

interface PostActionDescriberInterface extends PostActionAwareInterface
{
    public function describePostAction(callable|PostActionDescriptorInterface $callback, string|array|null $action = null): void;
}
