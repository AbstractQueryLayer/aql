<?php

declare(strict_types=1);

namespace IfCastle\AQL\DTO;

/** A slot of AqlRoundTripSlot with both key columns marked as the primary key. */
#[DtoMap('AqlRoundTripSlot')]
class RoundTripSlotDto extends DataTransferObjectAbstract
{
    public function __construct(
        #[Map(isPrimaryKey: true)]
        public int $roundTripId,
        #[Map(isPrimaryKey: true)]
        public int $position,
        #[Map]
        public string $label,
    ) {}
}
