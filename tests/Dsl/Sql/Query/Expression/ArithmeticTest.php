<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\Exceptions\UnexpectedValue;
use PHPUnit\Framework\TestCase;

class ArithmeticTest extends TestCase
{
    public function testLeftNestedChainKeepsItsOrderWithoutParentheses(): void
    {
        $chain = new Arithmetic(
            new Arithmetic(new Column('a'), Arithmetic::MINUS, new Constant(1)),
            Arithmetic::MINUS,
            new Constant(2)
        );

        $this->assertSame('a - 1 - 2', $chain->getAql());
    }

    public function testRightNestedArithmeticIsParenthesized(): void
    {
        $nested = new Arithmetic(
            new Column('a'),
            Arithmetic::MINUS,
            new Arithmetic(new Column('b'), Arithmetic::MINUS, new Constant(1))
        );

        $this->assertSame('a - (b - 1)', $nested->getAql());
    }

    public function testIntervalAmountMayBeAnIntegerOrANode(): void
    {
        $this->assertSame('INTERVAL 5 DAY', (new TimeInterval(TimeIntervalEnum::DAY, 5))->getAql());
        $this->assertSame(
            'INTERVAL ttl HOUR',
            (new TimeInterval(TimeIntervalEnum::HOUR, new Column('ttl')))->getAql()
        );
    }

    public function testOperatorOtherThanPlusOrMinusIsRejected(): void
    {
        $this->expectException(UnexpectedValue::class);

        new Arithmetic(new Column('a'), '*', new Constant(1));
    }
}
