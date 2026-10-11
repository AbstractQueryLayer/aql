<?php

declare(strict_types=1);

namespace IfCastle\AQL\StoragePool\ReadWritePool;

use IfCastle\AQL\Executor\AdditionalOptions;
use IfCastle\AQL\Transaction\TransactionInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SqlReadWritePoolTest extends TestCase
{
    private RecordingSqlStorage $reader;

    private RecordingSqlStorage $writer;

    private SqlReadWritePool $pool;

    #[\Override]
    protected function setUp(): void
    {
        $this->reader               = new RecordingSqlStorage('reader');
        $this->writer               = new RecordingSqlStorage('writer');
        $this->pool                 = new SqlReadWritePool(fn() => $this->reader, fn() => $this->writer);
    }

    // The pool sends everything to the writer until the writer is instantiated; reads reach the reader after that.
    private function instantiateWriter(): void
    {
        $this->pool->executeSql('INSERT INTO t VALUES (1)');
        $this->writer->calls        = [];
    }

    public function testSelectWithoutContextGoesToReader(): void
    {
        $this->instantiateWriter();
        $this->pool->executeSql('SELECT 1');

        $this->assertCount(1, $this->reader->calls);
        $this->assertSame([], $this->writer->calls);
    }

    public function testSelectWithContextWithoutTransactionGoesToReaderWithContext(): void
    {
        $this->instantiateWriter();
        $context                    = new AdditionalOptions([]);

        $this->pool->executeSql('SELECT 1', $context);

        $this->assertCount(1, $this->reader->calls);
        $this->assertSame($context, $this->reader->calls[0]['context']);
        $this->assertSame([], $this->writer->calls);
    }

    #[DataProvider('sqlProvider')]
    public function testContextWithTransactionGoesToWriterWithContext(string $sql): void
    {
        $this->instantiateWriter();
        $context                    = new AdditionalOptions([], $this->createStub(TransactionInterface::class));

        $this->pool->executeSql($sql, $context);

        $this->assertSame([], $this->reader->calls, 'A statement inside a transaction must not reach the reader');
        $this->assertCount(1, $this->writer->calls);
        $this->assertSame($sql, $this->writer->calls[0]['sql']);
        $this->assertSame($context, $this->writer->calls[0]['context']);
    }

    public static function sqlProvider(): array
    {
        return [
            'select'                => ['SELECT * FROM t'],
            'select for update'     => ['SELECT * FROM t WHERE id = 1 FOR UPDATE'],
            'with'                  => ['WITH x AS (SELECT 1) SELECT * FROM x'],
            'update'                => ['UPDATE t SET a = 1'],
        ];
    }

    public function testNonSelectWithoutTransactionGoesToWriterWithContext(): void
    {
        $this->instantiateWriter();
        $context                    = new AdditionalOptions([]);

        $this->pool->executeSql('DELETE FROM t', $context);

        $this->assertSame([], $this->reader->calls);
        $this->assertCount(1, $this->writer->calls);
        $this->assertSame($context, $this->writer->calls[0]['context']);
    }
}
