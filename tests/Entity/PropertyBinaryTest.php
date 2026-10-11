<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity;

use IfCastle\AQL\Dsl\Sql\Constant\BinaryString;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Entity\Property\PropertyBinary;
use IfCastle\AQL\MySql\Ddl\Generator\PropertyToColumn;
use IfCastle\AQL\PostgreSql\Storage\PostgreSql;
use IfCastle\TypeDefinitions\TypeBinary;
use PHPUnit\Framework\TestCase;

/** What a binary property declares outside a query: its column, its type and the PostgreSQL literal. */
final class PropertyBinaryTest extends TestCase
{
    public function testMySqlColumnIsFixedLengthBinary(): void
    {
        $column = (new PropertyToColumn(
            $this->createStub(EntityInterface::class),
            $this->createStub(EntityFactoryInterface::class),
            new PropertyBinary('hash', 32),
        ))->generate();

        $this->assertSame('`hash` BINARY (32) NOT NULL', $column->getResult());
    }

    public function testDefinitionIsBinaryOfTheColumnLength(): void
    {
        $definition = (new PropertyBinary('hash', 32))->getDefinition();

        $this->assertInstanceOf(TypeBinary::class, $definition);
        $this->assertTrue($definition->isBinary());
        $this->assertSame(32, $definition->getMaxLength());
        $this->assertSame(32, $definition->getMinLength());
    }

    /** The hex input format of bytea; quoting bytes needs no connection, so no server is reached. */
    public function testPostgreSqlWritesByteaHexLiteral(): void
    {
        $storage = new PostgreSql(['dsn' => 'pgsql:host=127.0.0.1;port=1;dbname=none']);

        $this->assertSame('\'\\x00ff27\'::bytea', $storage->quote(new BinaryString("\x00\xff'")));
    }
}
