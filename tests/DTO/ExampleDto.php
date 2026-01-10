<?php

declare(strict_types=1);

namespace IfCastle\AQL\DTO;

#[DtoMap('example')]
class ExampleDto extends DataTransferObjectAbstract
{
    public function __construct(
        #[Map('fullName')]
        public string $name,
        #[Map('fullAge', encodeKey: 'customerAge')]
        public int $age,
        #[Map(encodeKey: 'customerEmail')]
        public string $email,
        #[Map]
        public \DateTimeImmutable|null $createdAt,
        #[Map]
        public \DateTimeImmutable|null $updatedAt,
        #[Map(isPrimaryKey: true, isHidden: true)]
        public int $id = 0
    ) {}
}
