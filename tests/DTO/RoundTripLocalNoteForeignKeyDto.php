<?php

declare(strict_types=1);

namespace IfCastle\AQL\DTO;

/**
 * A note of AqlRoundTripLocalNote whose key property maps to the id of its AqlRoundTrip, not to its own: the filter
 * matches every note of that round trip, so its update must be refused.
 */
#[DtoMap('AqlRoundTripLocalNote')]
class RoundTripLocalNoteForeignKeyDto extends DataTransferObjectAbstract
{
    public function __construct(
        #[Map(property: 'id', isPrimaryKey: true, entityName: 'AqlRoundTrip')]
        public int $roundTripId,
        #[Map]
        public string $label,
    ) {}
}
