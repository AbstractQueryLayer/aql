<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;
use IfCastle\AQL\Dsl\Sql\Parameter\ParameterInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Limit as LimitNode;
use IfCastle\AQL\Dsl\Sql\Query\Expression\LimitWithParameters;
use IfCastle\Exceptions\UnexpectedMethodMode;
use IfCastle\Exceptions\UnexpectedValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LimitTest extends TestCase
{
    public function testNumbersOnlyGiveAPlainLimit(): void
    {
        $limit                      = (new Limit())->parse('LIMIT 5, 10');

        $this->assertInstanceOf(LimitNode::class, $limit);
        $this->assertSame(5, $limit->getOffset());
        $this->assertSame(10, $limit->getLimit());
    }

    public static function parameterForms(): array
    {
        return [
            'count'                 => ['LIMIT {n}', 'LIMIT {n}', 'LIMIT 3', ['n' => 3]],
            'offset and count'      => ['LIMIT {from}, {n}', 'LIMIT {from}, {n}', 'LIMIT 4, 3', ['from' => 4, 'n' => 3]],
            'number and count'      => ['LIMIT 4, {n}', 'LIMIT 4, {n}', 'LIMIT 4, 3', ['n' => 3]],
            'offset and number'     => ['LIMIT {from}, 3', 'LIMIT {from}, 3', 'LIMIT 4, 3', ['from' => 4]],
        ];
    }

    #[DataProvider('parameterForms')]
    public function testParameterIsParsedAndRenderedAsIntegers(string $aql, string $expectedAql, string $expectedSql, array $values): void
    {
        $limit                      = (new Limit())->parse($aql);

        $this->assertInstanceOf(LimitWithParameters::class, $limit);
        $this->assertSame($expectedAql, $limit->getAql());

        foreach ($values as $name => $value) {
            $this->parameterNamed($limit, $name)->setParameterValue($value);
        }

        $this->assertSame($expectedSql, $limit->getResult());
    }

    public static function notIntegers(): array
    {
        return [
            'numeric string'        => ['2'],
            'float'                 => [2.5],
            'text'                  => ['2; DROP TABLE x'],
            'null'                  => [null],
            'negative'              => [-1],
        ];
    }

    #[DataProvider('notIntegers')]
    public function testNonIntegerValueIsRejected(mixed $value): void
    {
        $limit                      = (new Limit())->parse('LIMIT {n}');
        $this->parameterNamed($limit, 'n')->setParameterValue($value);

        $this->expectException(UnexpectedValue::class);

        $limit->getResult();
    }

    public function testRenderingWithoutValueFailsInsteadOfDroppingTheLimit(): void
    {
        $limit                      = (new Limit())->parse('LIMIT {n}');

        $this->expectException(UnexpectedMethodMode::class);

        $limit->getResult();
    }

    public function testZeroCountStaysALimit(): void
    {
        $limit                      = (new Limit())->parse('LIMIT {n}');
        $this->parameterNamed($limit, 'n')->setParameterValue(0);

        $this->assertSame('LIMIT 0', $limit->getResult());
    }

    public function testMissingCountIsAParseError(): void
    {
        $this->expectException(ParseException::class);

        (new Limit())->parse('LIMIT name');
    }

    public function testUnclosedParameterIsAParseError(): void
    {
        $this->expectException(ParseException::class);

        (new Limit())->parse('LIMIT {n');
    }

    private function parameterNamed(LimitWithParameters $limit, string $name): ParameterInterface
    {
        foreach ($limit->getChildNodes() as $node) {
            if ($node instanceof ParameterInterface && $node->getParameterName() === $name) {
                return $node;
            }
        }

        $this->fail('No parameter ' . $name);
    }
}
