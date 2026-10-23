<?php

declare(strict_types=1);

namespace IfCastle\AQL\PdoDriver;

use IfCastle\AQL\Dsl\Sql\Constant\BinaryString;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Parameter\Parameter;
use IfCastle\Exceptions\UnexpectedValue;
use IfCastle\Exceptions\UnexpectedValueType;
use PHPUnit\Framework\TestCase;

/**
 * Binding of the PDO statement adapter, on SQLite in memory: each value reaches the statement with its type.
 */
class PDOStatementAdapterTest extends TestCase
{
    public function testEachValueIsBoundWithTheTypeOfItsValue(): void
    {
        $adapter = $this->adapter(
            'SELECT typeof(:int), typeof(:null), typeof(:float), :float, typeof(:text), typeof(:blob), :blob,'
            . ' :date, :stringable, typeof(:binary)'
        );

        $values = [
            ':int'          => 7,
            ':null'         => null,
            ':float'        => 1.5,
            ':text'         => 'text',
            ':blob'         => new BinaryString("\x00\xff"),
            ':date'         => new \DateTimeImmutable('2026-10-22 13:14:15.5'),
            ':stringable'   => new class implements \Stringable {
                public function __toString(): string
                {
                    return 'stringable';
                }
            },
        ];

        foreach ($values as $placeholder => $value) {
            $adapter->bindParameter((new Constant($value))->asPlaceholder($placeholder));
        }

        $adapter->bindParameter((new Constant('bytes'))->asBinary()->asPlaceholder(':binary'));
        $adapter->bindValues();
        $adapter->getPdoStatement()->execute();

        // A float goes as text: PDO has no float type, and SQLite keeps the text it is given.
        $this->assertSame(
            ['integer', 'null', 'text', '1.5', 'text', 'blob', "\x00\xff", '2026-10-22 13:14:15', 'stringable', 'blob'],
            $adapter->getPdoStatement()->fetch(\PDO::FETCH_NUM)
        );
    }

    public function testABoundParameterIsReadWhenTheStatementExecutes(): void
    {
        $adapter = $this->adapter('SELECT :value');
        $parameter = (new Parameter('value'))->setParameterValue('first')->asPlaceholder(':value');
        $adapter->bindParameter($parameter);

        $parameter->setParameterValue('second');

        $this->assertSame(['second'], $this->execute($adapter));
    }

    public function testAConstantWithoutANamedPlaceholderIsRefused(): void
    {
        $adapter = $this->adapter('SELECT 1');

        foreach ([new Constant(1), (new Constant(1))->asPlaceholder()] as $constant) {
            try {
                $adapter->bindParameter($constant);
                $this->fail('A constant without a named placeholder was bound');
            } catch (UnexpectedValue) {
                $this->assertSame([], $adapter->getParameterDefinitions());
            }
        }
    }

    public function testAValueWithoutAnSqlFormIsRefused(): void
    {
        $adapter = $this->adapter('SELECT :list');
        $adapter->bindParameter((new Constant([1, 2]))->asPlaceholder(':list'));

        $this->expectException(UnexpectedValueType::class);
        $adapter->bindValues();
    }

    public function testAPlaceholderTheStatementLacksFailsTheExecution(): void
    {
        $adapter = $this->adapter('SELECT :a');
        $adapter->bindParameter((new Constant(1))->asPlaceholder(':missing'));

        $this->expectException(\PDOException::class);
        $this->execute($adapter);
    }

    private function adapter(string $sql): PDOStatementAdapter
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        return new PDOStatementAdapter($pdo->prepare($sql));
    }

    /**
     * @return list<mixed>
     */
    private function execute(PDOStatementAdapter $adapter): array
    {
        $adapter->bindValues();
        $adapter->getPdoStatement()->execute();

        return $adapter->getPdoStatement()->fetch(\PDO::FETCH_NUM);
    }
}
