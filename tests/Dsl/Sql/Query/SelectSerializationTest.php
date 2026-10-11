<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query;

use PHPUnit\Framework\TestCase;

class SelectSerializationTest extends TestCase
{
    public function testSelectWithEmptySlotsSerialises(): void
    {
        $array                      = Select::from('Test')->limit(3)->toArray();

        $this->assertSame(Select::ACTION_SELECT, $array[Select::ACTION]);
        $this->assertNull($array[Select::LOCK_MODE]);
        $this->assertNotEmpty($array[Select::CHILD_NODES]);
    }

    public function testLockModeIsSerialised(): void
    {
        $this->assertSame('FOR UPDATE', Select::from('Test')->forUpdate()->toArray()[Select::LOCK_MODE]);
        $this->assertSame('LOCK IN SHARE MODE', Select::from('Test')->lockInShareMode()->toArray()[Select::LOCK_MODE]);
    }

    public function testFromArrayRestoresTheLockMode(): void
    {
        $this->assertSame(LockMode::FOR_UPDATE, Select::fromArray([Select::LOCK_MODE => 'FOR UPDATE'])->getLockMode());
        $this->assertSame(LockMode::SHARE, Select::fromArray([Select::LOCK_MODE => 'LOCK IN SHARE MODE'])->getLockMode());
        $this->assertNull(Select::fromArray([Select::LOCK_MODE => null])->getLockMode());
        $this->assertNull(Select::fromArray([])->getLockMode());
    }

    public function testLockWaitRoundTripsWithItsLockMode(): void
    {
        $array = Select::from('Test')->forUpdate()->setLockWait(LockWait::SKIP_LOCKED)->toArray();

        $this->assertSame('SKIP LOCKED', $array[Select::LOCK_WAIT]);
        // Select::fromArray cannot rebuild a whole serialised tree yet (PLAN Fog), so only the lock keys go back.
        $restored = Select::fromArray(
            [Select::LOCK_MODE => $array[Select::LOCK_MODE], Select::LOCK_WAIT => $array[Select::LOCK_WAIT]]
        );
        $this->assertSame(LockWait::SKIP_LOCKED, $restored->getLockWait());
        $this->assertNull(Select::fromArray([Select::LOCK_WAIT => 'NOWAIT'])->getLockWait());
    }
}
