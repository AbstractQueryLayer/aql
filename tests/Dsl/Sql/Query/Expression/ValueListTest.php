<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Sql\Query\Expression;

use IfCastle\AQL\Dsl\Parser\ValueList as ValueListParser;
use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use PHPUnit\Framework\TestCase;

class ValueListTest extends TestCase
{
    public function testWalkValues(): void
    {
        $valueList = (new ValueListParser())->parse('(column1, column2, column3) VALUES (1, 2, 3)');

        foreach ($valueList->walkValues('column1') as [$results, $set]) {
            $this->assertIsArray($results);
            $this->assertInstanceOf(ConstantInterface::class, $results['column1']);
            $this->assertInstanceOf(NodeList::class, $set);
        }
    }

    public function testAppendColumnToSet(): void
    {
        $valueList = (new ValueListParser())->parse('(column1) VALUES (1)');

        $valueList->appendColumnToSet('column2', new Constant(4));

        foreach ($valueList->walkValues('column2') as [$results, $set]) {
            $this->assertIsArray($results);
            $this->assertInstanceOf(ConstantInterface::class, $results['column2']);
            $this->assertSame(4, $results['column2']->getConstantValue());
        }
    }

    public function testIsListEmpty(): void
    {
        $valueList                  = new ValueList();

        $this->assertTrue($valueList->isListEmpty());
    }

    public function testFindValues(): void
    {
        $valueList                  = (new ValueListParser())->parse('(column1, column2, column3) VALUES (1, 2, 3)');

        $values                     = $valueList->findValues('column1');

        $this->assertIsArray($values);
        $this->assertArrayHasKey('column1', $values);
        $this->assertIsArray($values['column1']);
        $this->assertInstanceOf(ConstantInterface::class, $values['column1'][0]);
    }

    public function testAppendColumn(): void
    {
        $valueList                  = new ValueList();

        $valueList->appendColumn('column1');

        $this->assertFalse($valueList->isListEmpty());
    }

    public function testFindColumnOffset(): void
    {
        $valueList                  = (new ValueListParser())->parse('(column1, column2, column3) VALUES (1, 2, 3)');

        $this->assertSame(0, $valueList->findColumnOffset('column1'));
        $this->assertSame(1, $valueList->findColumnOffset('column2'));
        $this->assertSame(2, $valueList->findColumnOffset('column3'));
    }

    public function testGetColumns(): void
    {
        $valueList                  = (new ValueListParser())->parse('(column1, column2, column3) VALUES (1, 2, 3)');

        $columns                    = $valueList->getColumns();

        $this->assertIsArray($columns);
        $this->assertCount(3, $columns);
    }

    public function testIsListNotEmpty(): void
    {
        $valueList                  = new ValueList();

        $this->assertFalse($valueList->isListNotEmpty());
    }

    public function testAddChildNode(): void
    {
        $valueList                  = new ValueList();

        $valueList->addChildNode(new Column('column1'));
        $valueList->addChildNode(new NodeList(new Constant(1)));

        $this->assertTrue($valueList->isListNotEmpty());
        $this->assertSame('(column1) VALUES (1)', $valueList->getAql());
    }

    public function testDefineValues(): void
    {
        $valueList                  = new ValueList('column1', 'column2', 'column3');

        $valueList->defineValues([
            ['column1' => 1, 'column2' => 2, 'column3' => 3],
            ['column1' => 4, 'column2' => 5, 'column3' => 6],
        ]);

        $this->assertSame('(column1, column2, column3) VALUES (1, 2, 3), (4, 5, 6)', $valueList->getAql());
    }

    public function testAppendColumnWithGenerator(): void
    {
        $valueList                  = new ValueList();

        $valueList->appendColumnWithGenerator('column1', function () {
            return new Constant(1);
        });

        $this->assertFalse($valueList->isListEmpty());

        $this->assertSame('(column1)', $valueList->getAql());
    }

    public function testFindColumn(): void
    {
        $valueList                  = (new ValueListParser())->parse('(column1, column2, column3) VALUES (1, 2, 3)');

        $column                     = $valueList->findColumn('column1');

        $this->assertInstanceOf(Column::class, $column);
    }

    public function testAppendValueList(): void
    {
        $valueList                  = new ValueList('column1');

        $valueList->appendValueList(new NodeList(new Constant(1)));

        $this->assertFalse($valueList->isListEmpty());

        $this->assertSame('(column1) VALUES (1)', $valueList->getAql());
    }
}
