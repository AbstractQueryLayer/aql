<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Tuple;

use PHPUnit\Framework\TestCase;

class TupleTest extends TestCase
{
    public function testAddTupleColumn(): void
    {
        $tuple                      = new Tuple();
        $tuple->addTupleColumn($this->createMock(TupleColumnInterface::class));
        $this->assertCount(1, $tuple->getTupleColumns());
    }

    public function testWhetherDefault(): void
    {
        $tuple                      = new Tuple();
        $tuple->markAsDefaultColumns();
        $this->assertTrue($tuple->whetherDefault());
    }

    public function testGetHiddenColumns(): void
    {
        $tuple                      = new Tuple();
        $tuple->addHiddenColumn($this->createMock(TupleColumnInterface::class));
        $this->assertCount(1, $tuple->getHiddenColumns());
    }

    public function testFindTupleColumn(): void
    {
        $tuple                      = new Tuple();
        $tuple->addTupleColumn($column = $this->createMock(TupleColumnInterface::class), 'alias');
        $this->assertEquals($column, $tuple->findTupleColumn('alias'));
    }
}
