<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Parameter\Parameter;
use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;
use PHPUnit\Framework\TestCase;

class LimitTest extends TestCase
{
    private function roundTrip(LimitInterface $limit): LimitInterface
    {
        $restored                   = ArrayTyped::unserialize(ArrayTyped::serialize($limit));
        $this->assertInstanceOf($limit::class, $restored);

        return $restored;
    }

    public function testLimitKeepsCountAndOffset(): void
    {
        $restored                   = $this->roundTrip(new Limit(5, 2));

        $this->assertSame(5, $restored->getLimit());
        $this->assertSame(2, $restored->getOffset());
        $this->assertSame('LIMIT 2, 5', $restored->getAql());
    }

    public function testEmptyLimitStaysEmpty(): void
    {
        $restored                   = $this->roundTrip(new Limit());

        $this->assertFalse($restored->isNotEmpty());
    }

    public function testLimitWithConstantsKeepsCountAndOffset(): void
    {
        $restored                   = $this->roundTrip(new LimitWithParameters(new Constant(5), new Constant(2)));

        $this->assertSame(5, $restored->getLimit());
        $this->assertSame(2, $restored->getOffset());
        $this->assertSame('LIMIT 2, 5', $restored->getAql());
    }

    public function testLimitWithResolvedParametersKeepsNamesAndValues(): void
    {
        $limit                      = new LimitWithParameters(
            (new Parameter('count'))->setParameterValue(7),
            (new Parameter('skip'))->setParameterValue(3)
        );
        $restored                   = $this->roundTrip($limit);

        $this->assertSame('LIMIT {skip}, {count}', $restored->getAql());
        $this->assertSame(7, $restored->getLimit());
        $this->assertSame(3, $restored->getOffset());
    }

    public function testLimitWithUnresolvedParameterStaysUnresolved(): void
    {
        $restored                   = $this->roundTrip(new LimitWithParameters(new Parameter('count'), new Constant(0), false));

        $this->assertSame('LIMIT {count}', $restored->getAql());
        $this->assertSame(0, $restored->getLimit());
    }
}
