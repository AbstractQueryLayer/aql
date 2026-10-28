<?php

declare(strict_types=1);

namespace IfCastle\AQL\Jobs;

use IfCastle\Jobs\JobKinds;
use IfCastle\Jobs\JobWorker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The job kind registry, without a database. */
final class JobKindsTest extends TestCase
{
    public function testWorkerLeasesTheRegisteredKindsInOrder(): void
    {
        $kinds = (new JobKinds())->handle('b', static fn() => null)->handle('a', static fn() => null);

        $worker = $kinds->worker((new \ReflectionClass(\IfCastle\Jobs\JobQueue::class))
            ->newInstanceWithoutConstructor(), 30);

        $this->assertSame(['b', 'a'], $kinds->kinds());
        $this->assertSame(['b', 'a'], \array_keys((new \ReflectionProperty(JobWorker::class, 'handlers'))
            ->getValue($worker)));
    }

    public function testASecondHandlerForAKindIsRefused(): void
    {
        $kinds = (new JobKinds())->handle('a', static fn() => null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Job kind a already has a handler');
        $kinds->handle('a', static fn() => null);
    }

    public function testAPeriodicKindWithoutAHandlerIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Job kind a is scheduled but not handled');
        (new JobKinds())->every('a', 60);
    }

    /** @return iterable<string, array{string}> */
    public static function badKinds(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [\str_repeat('k', JobKinds::MAX_KIND_LENGTH + 1)];
        yield 'a space' => ['mail send'];
        yield 'not ASCII' => ['почта'];
    }

    #[DataProvider('badKinds')]
    public function testAKindTheTableCannotHoldIsRefused(string $kind): void
    {
        $this->expectException(\LogicException::class);
        (new JobKinds())->handle($kind, static fn() => null);
    }

    public function testTheLongestKindIsAccepted(): void
    {
        $kind = \str_repeat('k', JobKinds::MAX_KIND_LENGTH);

        $this->assertSame([$kind], (new JobKinds())->handle($kind, static fn() => null)->kinds());
    }
}
