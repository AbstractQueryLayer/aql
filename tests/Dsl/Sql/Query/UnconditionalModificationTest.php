<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\AQL\Dsl\Sql\Query\Exceptions\TransformationException;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Where;
use PHPUnit\Framework\TestCase;

/**
 * UPDATE and DELETE without a condition render only when asked for every row: a condition lost by a bug must not
 * change every row.
 */
class UnconditionalModificationTest extends TestCase
{
    public function testDeleteWithoutWhereIsRefused(): void
    {
        $this->expectException(TransformationException::class);

        Delete::entity('Test')->getResult();
    }

    public function testUpdateWithoutWhereIsRefused(): void
    {
        $this->expectException(TransformationException::class);

        Update::entity('Test')->assign('name', 'x')->getResult();
    }

    public function testConditionRenders(): void
    {
        $this->assertIsString(Delete::entity('Test', (new Where())->equal('id', 5))->getResult());
        $this->assertIsString(Update::entity('Test', (new Where())->equal('id', 5))->assign('name', 'x')->getResult());
    }

    public function testEveryRowWhenAskedFor(): void
    {
        $this->assertStringNotContainsString('WHERE', (string) Delete::entity('Test')->unconditional()->getResult());
        $this->assertStringNotContainsString('WHERE', (string) Update::entity('Test')->assign('name', 'x')
            ->unconditional()->getResult());
    }
}
