<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use PHPUnit\Framework\TestCase;

class SelectLockTest extends TestCase
{
    public function testOrdinarySelectHasAnEmptyLock(): void
    {
        $select = Select::from('Test');

        $this->assertNull($select->getLockMode());
        $this->assertSame('SELECT * FROM Test', $select->getAql());
    }

    public function testForUpdateRendersAfterLimit(): void
    {
        $select = Select::from('Test')->limit(3)->forUpdate();

        $this->assertSame(LockMode::FOR_UPDATE, $select->getLockMode());
        $this->assertSame('SELECT * FROM Test LIMIT 0, 3 FOR UPDATE', $select->getAql());
        $this->assertStringEndsWith(' LIMIT 3 FOR UPDATE', $this->resultOf($select));
    }

    public function testLockInShareModeKeepsItsFormWhileForShareIsRenderedOnRequest(): void
    {
        $select = Select::from('Test')->lockInShareMode();

        $this->assertStringEndsWith(' LOCK IN SHARE MODE', $this->resultOf($select));
        $this->assertStringEndsWith(' FOR SHARE', $select->getResultWithForShare());

        $this->assertStringEndsWith(' LOCK IN SHARE MODE', $this->resultOf($select));
        $this->assertSame('SELECT * FROM Test LOCK IN SHARE MODE', $select->getAql());
    }

    public function testForUpdateAndOrdinaryReadAreUnaffectedByTheShareForm(): void
    {
        $forUpdate = Select::from('Test')->forUpdate();
        $ordinary = Select::from('Test');

        $this->assertSame($this->resultOf($forUpdate), $forUpdate->getResultWithForShare());
        $this->assertStringEndsWith(' FOR UPDATE', $forUpdate->getResultWithForShare());
        $this->assertSame($this->resultOf($ordinary), $ordinary->getResultWithForShare());
    }

    public function testLockSurvivesClone(): void
    {
        $select = Select::from('Test')->lockInShareMode();

        $this->assertSame('SELECT * FROM Test LOCK IN SHARE MODE', (clone $select)->getAql());
        $this->assertSame(LockMode::SHARE, (clone $select)->getLockMode());
    }

    public function testSetLockModeNullMakesAnOrdinaryRead(): void
    {
        $select = Select::from('Test')->forUpdate()->setLockMode(null);

        $this->assertSame('SELECT * FROM Test', $select->getAql());
    }

    private function resultOf(Select $select): string
    {
        return (string) $select->getResult();
    }
}
