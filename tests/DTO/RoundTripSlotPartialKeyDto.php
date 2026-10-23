<?php

declare(strict_types=1);

namespace IfCastle\AQL\DTO;

/** A slot of AqlRoundTripSlot that marks only the first key column: its update must be refused. */
#[DtoMap('AqlRoundTripSlot')]
class RoundTripSlotPartialKeyDto extends DataTransferObjectAbstract
{
    public function __construct(
        #[Map(isPrimaryKey: true)]
        public int $roundTripId,
        #[Map]
        public int $position,
        #[Map]
        public string $label,
    ) {}
}
