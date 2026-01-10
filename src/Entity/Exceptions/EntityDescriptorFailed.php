<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Exceptions;

class EntityDescriptorFailed extends EntityDescriptorException
{
    protected string $template      = 'The entity {entity} descriptor failed. {reason}';

    public function __construct(string $entityName, string $reason)
    {
        parent::__construct([
            'entity'                => $entityName,
            'reason'                => $reason,
        ]);
    }

}
