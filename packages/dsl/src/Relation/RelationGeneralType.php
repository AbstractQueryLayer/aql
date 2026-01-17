<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Relation;

/**
 * Classic relationships between entities in a database.
 */
enum RelationGeneralType: string
{
    /**
     * The relation of sets to each other.
     * @var string
     */
    case ONE_TO_ONE                 = 'one_to_one';

    /**
     * @var string
     */
    case ONE_TO_MANY                = 'one_to_many';

    /**
     * @var string
     */
    case MANY_TO_ONE                = 'many_to_one';

    /**
     * @var string
     */
    case MANY_TO_MANY               = 'many_to_many';
}
