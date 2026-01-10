<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Modifiers;

class Pagination extends Modifier
{
    public function __construct()
    {
        parent::__construct('pagination');
    }

}
