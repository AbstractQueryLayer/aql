<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QueryOptionsTest extends TestCase
{
    public static function setValues(): array
    {
        return [
            'true'                  => [true],
            'int zero'              => [0],
            'string zero'           => ['0'],
            'float zero'            => [0.0],
            'int one'               => [1],
            'string'                => ['on'],
        ];
    }

    #[DataProvider('setValues')]
    public function testOptionWithValueIsSet(bool|int|float|string $value): void
    {
        $options                    = (new QueryOptions())->addOption(new QueryOption('flag', $value));

        $this->assertTrue($options->isOption('flag'));
    }

    public function testOptionSetToFalseIsOff(): void
    {
        $options                    = (new QueryOptions())->addOption(new QueryOption('flag', false));

        $this->assertFalse($options->isOption('flag'));
    }

    public function testRedefinedToFalseIsOff(): void
    {
        $options                    = (new QueryOptions())
            ->addOption(new QueryOption('flag', true))
            ->addOption(new QueryOption('flag', false));

        $this->assertFalse($options->isOption('flag'));
    }

    public function testMissingOptionIsOff(): void
    {
        $this->assertFalse((new QueryOptions())->isOption('flag'));
    }
}
