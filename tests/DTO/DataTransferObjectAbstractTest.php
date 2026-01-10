<?php

declare(strict_types=1);

namespace IfCastle\AQL\DTO;

use IfCastle\AQL\Dsl\Sql\Column\Column;
use IfCastle\AQL\Dsl\Sql\Constant\Constant;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\Equal;
use IfCastle\AQL\Dsl\Sql\Query\Select;
use IfCastle\AQL\DTO\Exceptions\RecordNotFound;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Key\Key;
use IfCastle\AQL\Entity\Populator\Dataset;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\Entity\Property\PropertyString;
use IfCastle\AQL\Entity\Property\PropertyTimestamp;
use IfCastle\AQL\TestCases\TestCaseWithSqlMemoryDb;

class DataTransferObjectAbstractTest extends TestCaseWithSqlMemoryDb
{
    final public const string EXAMPLE = 'example';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->defineEntities();
    }

    public function testFetch(): void
    {
        [$expectedRecords]          = $this->defineDataset();

        $records                    = ExampleDto::fetch($this->getAqlExecutor());

        foreach ($records as $record) {
            $this->assertArrayHasKey($record->name, $expectedRecords, 'Unexpected record name');

            $actualRecord           = $record->extract();
            unset($actualRecord['id']);

            $this->assertEquals($expectedRecords[$record->name], $actualRecord, 'Unexpected record data');
        }
    }

    public function testFetchOne(): void
    {
        [$expectedRecords]          = $this->defineDataset();

        $record                     = ExampleDto::fetchOne($this->getAqlExecutor());

        $this->assertArrayHasKey($record->name, $expectedRecords, 'Unexpected record name');

        $actualRecord               = $record->extract();
        unset($actualRecord['id']);

        $this->assertEquals($expectedRecords[$record->name], $actualRecord, 'Unexpected record data');
    }

    public function testFindById(): void
    {
        [$expectedRecords]          = $this->defineDataset();

        $record                     = ExampleDto::findById($this->getAqlExecutor(), 1);

        $this->assertArrayHasKey($record->name, $expectedRecords, 'Unexpected record name');

        $actualRecord               = $record->extract();
        unset($actualRecord['id']);

        $this->assertEquals($expectedRecords[$record->name], $actualRecord, 'Unexpected record data');
    }

    public function testFindByIdNotFound(): void
    {
        $this->defineDataset();

        $record                     = ExampleDto::findById($this->getAqlExecutor(), 15);

        $this->assertNull($record, 'Unexpected record');
    }

    public function testFetchById(): void
    {
        [$expectedRecords]          = $this->defineDataset();

        $record                     = ExampleDto::fetchById($this->getAqlExecutor(), 1);

        $this->assertArrayHasKey($record->name, $expectedRecords, 'Unexpected record name');

        $actualRecord               = $record->extract();
        unset($actualRecord['id']);

        $this->assertEquals($expectedRecords[$record->name], $actualRecord, 'Unexpected record data');
    }

    public function testFetchByIdNotFound(): void
    {
        $this->defineDataset();
        $this->expectException(RecordNotFound::class);

        $record                     = ExampleDto::fetchById($this->getAqlExecutor(), 15);

        $this->assertNull($record, 'Unexpected record');
    }

    public function testCount(): void
    {
        [$expectedRecords]          = $this->defineDataset();

        $count                      = ExampleDto::count($this->getAqlExecutor());

        $this->assertEquals(\count($expectedRecords), $count, 'Unexpected count');
    }

    public function testInsert(): void
    {
        $this->defineDataset();
        $aqlExecutor                = $this->getAqlExecutor();

        $record                     = new ExampleDto(
            'Robert Doe',
            37,
            'robert@domain.com',
            new \DateTimeImmutable('2021-01-01 00:10:00'),
            new \DateTimeImmutable('2021-02-01 00:20:00')
        );

        $insertedRecord             = $record->insert($aqlExecutor);
        $select                     = new Select(from: 'example', where: [new Equal(new Column('fullName'), new Constant('Robert Doe'))]);

        $expected                   = $aqlExecutor->executeAql($select)->getFirstOrNull();

        $this->assertNotEmpty($expected, 'Expected record not found');
        $this->assertEquals($expected['id'], $insertedRecord->id, 'Unexpected id');
        $this->assertEquals($expected['fullName'], $insertedRecord->name, 'Unexpected firstName');
    }

    public function testUpdate(): void
    {
        $this->defineDataset();
        $aqlExecutor                = $this->getAqlExecutor();

        $record                     = ExampleDto::findById($aqlExecutor, 1);

        $record->name               = 'Robert Doe Updated';

        $record->update($aqlExecutor);
        $select                     = new Select(from: 'example', where: [new Equal(new Column('fullName'), new Constant($record->name))]);

        $expected                   = $aqlExecutor->executeAql($select)->getFirstOrNull();

        $this->assertNotEmpty($expected, 'Expected record not found');
        $this->assertEquals($expected['id'], $record->id, 'Unexpected id');
        $this->assertEquals($expected['fullName'], $record->name, 'Unexpected firstName');
    }

    public function testDelete(): void
    {
        $this->defineDataset();
        $aqlExecutor                = $this->getAqlExecutor();

        $record                     = ExampleDto::findById($aqlExecutor, 1);

        $record->delete($aqlExecutor);

        $select                     = new Select(
            from: 'example',
            where: [new Equal(new Column('fullName'), new Constant($record->name))]
        );

        $expected                   = $aqlExecutor->executeAql($select)->getFirstOrNull();

        $this->assertEmpty($expected, 'Expected record not found');
    }

    public function testDecode(): void
    {
        $rawData                    = [
            'name'                  => 'John Doe',
            'customerAge'           => 18,
            'customerEmail'         => 'john@doe.com',
            'createdAt'             => '2021-01-01 00:10:00',
            'updatedAt'             => '2021-02-01 00:20:00',
        ];

        $record                     = ExampleDto::definition()->decode($rawData);

        $this->assertInstanceOf(ExampleDto::class, $record, 'Unexpected record type');

        $this->assertEquals($rawData['name'], $record->name, 'Unexpected name');
        $this->assertEquals($rawData['customerAge'], $record->age, 'Unexpected age');
        $this->assertEquals($rawData['customerEmail'], $record->email, 'Unexpected email');
        $this->assertEquals($rawData['createdAt'], $record->createdAt->format('Y-m-d H:i:s'), 'Unexpected createdAt');
        $this->assertEquals($rawData['updatedAt'], $record->updatedAt->format('Y-m-d H:i:s'), 'Unexpected updatedAt');
    }

    public function testEncode(): void
    {
        [$expectedRecords]          = $this->defineDataset();

        $record                     = ExampleDto::findById($this->getAqlExecutor(), 1);
        $encoded                    = $record->containerSerialize();

        $expectedRecord             = $expectedRecords[$record->name];

        $this->assertEquals($expectedRecord['fullName'], $encoded['name'], 'Unexpected name');
        $this->assertEquals($expectedRecord['fullAge'], $encoded['customerAge'], 'Unexpected age');
        $this->assertEquals($expectedRecord['email'], $encoded['customerEmail'], 'Unexpected email');
        $this->assertEquals($expectedRecord['createdAt']->format('Y-m-d H:i:s'), $encoded['createdAt'], 'Unexpected createdAt');
        $this->assertEquals($expectedRecord['updatedAt']->format('Y-m-d H:i:s'), $encoded['updatedAt'], 'Unexpected updatedAt');

        // Check if encoded data has no id
        $this->assertArrayNotHasKey('id', $encoded, 'Unexpected id');
    }

    protected function defineDataset(): array
    {
        $dataset                    = Dataset::instantiate($this->getDiContainer());
        $dataset->asRemoveExisted();

        $examplePopulator           = $dataset->instantiatePopulator(self::EXAMPLE);

        $expectedRecords            = [];
        $expectedRecords['John Doe'] = [
            'fullName'              => 'John Doe',
            'fullAge'               => 18,
            'email'                 => 'test-email@mydomain.com',
            'createdAt'             => new \DateTimeImmutable('2021-01-01 00:10:00'),
            'updatedAt'             => new \DateTimeImmutable('2021-02-01 00:20:00'),
        ];

        $expectedRecords['Alice Doe'] = [
            'fullName'          => 'Alice Doe',
            'fullAge'           => 19,
            'email'             => 'xxx-email@mydomain.com',
            'createdAt'         => new \DateTimeImmutable('2022-01-01 00:30:00'),
            'updatedAt'         => new \DateTimeImmutable('2022-02-01 00:40:00'),
        ];

        $expectedRecords['Clark Doe'] = [
            'fullName'          => 'Clark Doe',
            'fullAge'           => 78,
            'email'             => 'clack@mydomain.net',
            'createdAt'         => new \DateTimeImmutable('2023-11-30 00:21:00'),
            'updatedAt'         => new \DateTimeImmutable('2024-12-29 00:22:00'),
        ];

        $examplePopulator->setTemplateData($expectedRecords)->populate();

        return [$expectedRecords, $examplePopulator, $dataset];
    }

    protected function defineEntities(): void
    {
        $entity                     = new class extends EntityAbstract {
            protected function beforeBuild(): void
            {
                $this->name         = \ucfirst(DataTransferObjectAbstractTest::EXAMPLE);
            }

            protected function buildAspects(): void {}

            protected function buildProperties(): void
            {
                $this->describeProperty(
                    (new PropertyInteger('id'))
                        ->setTypicalName('id')
                        ->asPrimaryKey()
                        ->asAutoIncrement()
                )
                     ->describeProperty(new PropertyString('fullName'))
                     ->describeProperty(new PropertyInteger('fullAge'))
                     ->describeProperty(new PropertyString('email'))
                     ->describeProperty(new PropertyTimestamp('createdAt'))
                     ->describeProperty(new PropertyTimestamp('updatedAt'));

                $this->describeKey((new Key('id'))->asPrimary());
            }
        };


        $this->getEntityFactory()->setEntity($entity);
    }
}
