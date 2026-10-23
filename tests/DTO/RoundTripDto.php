<?php

declare(strict_types=1);

namespace IfCastle\AQL\DTO;

/** A row of AqlRoundTrip, whose single-column key comes from the PrimaryKey aspect without a typical name. */
#[DtoMap('AqlRoundTrip')]
class RoundTripDto extends DataTransferObjectAbstract
{
    public function __construct(
        #[Map]
        public string $value,
        #[Map(isPrimaryKey: true)]
        public int $id = 0,
    ) {}
}
