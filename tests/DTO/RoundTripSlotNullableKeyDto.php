<?php

declare(strict_types=1);

namespace IfCastle\AQL\DTO;

/** A slot of AqlRoundTripSlot whose position may be unknown: a write without it must be refused. */
#[DtoMap('AqlRoundTripSlot')]
class RoundTripSlotNullableKeyDto extends DataTransferObjectAbstract
{
    public function __construct(
        #[Map(isPrimaryKey: true)]
        public int $roundTripId,
        #[Map(isPrimaryKey: true)]
        public ?int $position,
        #[Map]
        public string $label,
    ) {}
}
