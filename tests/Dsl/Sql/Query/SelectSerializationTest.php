<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;
use PHPUnit\Framework\TestCase;

class SelectSerializationTest extends TestCase
{
    public function testSelectWithEmptySlotsSerialises(): void
    {
        $restored                   = $this->roundTrip(Select::from('Test')->limit(3));

        $this->assertSame(Select::ACTION_SELECT, $restored->getQueryAction());
        $this->assertSame(0, $restored->getWhere()->countOfOperations());
        $this->assertSame('LIMIT 0, 3', \trim($restored->getLimit()->getAql()));
    }

    public function testLockModeIsSerialised(): void
    {
        $this->assertSame(LockMode::FOR_UPDATE, $this->roundTrip(Select::from('Test')->forUpdate())->getLockMode());
        $this->assertSame(LockMode::SHARE, $this->roundTrip(Select::from('Test')->lockInShareMode())->getLockMode());
    }

    /** The stored form keeps the case by name, so it stays readable when the SQL text of a case changes. */
    public function testTheLockModeIsStoredByItsCase(): void
    {
        $this->assertSame(
            ['@enum' => LockMode::class, 'case' => 'FOR_UPDATE'],
            Select::from('Test')->forUpdate()->toArray()['lockMode']
        );
    }

    public function testFromArrayRestoresTheLockMode(): void
    {
        $this->assertNull(Select::fromArray([])->getLockMode());
        $this->assertNull($this->roundTrip(Select::from('Test'))->getLockMode());
        $this->assertNull($this->roundTrip(Select::from('Test')->forUpdate()->setLockMode(null))->getLockMode());
    }

    public function testLockWaitRoundTripsWithItsLockMode(): void
    {
        $restored                   = $this->roundTrip(
            Select::from('Test')->forUpdate()->setLockWait(LockWait::SKIP_LOCKED)
        );
        $this->assertSame(LockWait::SKIP_LOCKED, $restored->getLockWait());
        $this->assertSame(['@enum' => LockWait::class, 'case' => 'SKIP_LOCKED'], $restored->toArray()['lockWait']);

        // A wait without a lock mode renders nothing, so setLockMode(null) drops it and it is not written.
        $withoutMode                = Select::from('Test')->forUpdate()->setLockWait(LockWait::NOWAIT)
                                    ->setLockMode(null);
        $this->assertNull($this->roundTrip($withoutMode)->getLockWait());

        // Input written by hand with a wait and no lock mode gets no wait either.
        $array                      = Select::from('Test')->forUpdate()->setLockWait(LockWait::NOWAIT)->toArray();
        $array['lockMode']          = null;
        $this->assertNull(Select::fromArray($array)->getLockWait());
    }

    private function roundTrip(Select $query): Select
    {
        return ArrayTyped::unserialize(ArrayTyped::serialize($query));
    }
}
