<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool;

use IfCastle\AQL\MigrationTool\Executor\MigrationExecutor;
use IfCastle\AQL\MigrationTool\Executor\PhpMigrationExecutor;
use IfCastle\AQL\MigrationTool\Executor\SqlMigrationExecutor;
use IfCastle\AQL\MigrationTool\Repository\FileMigrationOperation;
use IfCastle\AQL\MigrationTool\Repository\MigrationEntity;
use IfCastle\AQL\MigrationTool\Repository\MigrationRepository;
use IfCastle\AQL\TestCases\TestCaseWithSqlMemoryDb;

class MigrationExecutorTest extends TestCaseWithSqlMemoryDb
{
    private MigrationExecutor $executor;
    private MigrationRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->defineEntities();
        $this->createMigrationTable();
        $this->setupExecutor();
    }

    private function defineEntities(): void
    {
        $entity = new MigrationEntity();
        $this->getEntityFactory()->setEntity($entity);
    }

    private function createMigrationTable(): void
    {
        $ddlStrategy = \IfCastle\AQL\Executor\Ddl\DdlStrategy::instantiate($this->getDiContainer());
        $ddlStrategy->asRemoveExisted()->defineEntity(MigrationEntity::entity());
    }

    private function setupExecutor(): void
    {
        $storage = $this->getMainStorage();
        $aqlExecutor = $this->getAqlExecutor();
        $this->repository = new MigrationRepository($aqlExecutor);

        $this->executor = new MigrationExecutor(
            $this->repository,
            [
                new SqlMigrationExecutor($storage),
                new PhpMigrationExecutor(),
            ]
        );
    }

    public function testExecuteMigrationSuccess(): void
    {
        $operation = new FileMigrationOperation(
            version: 1,
            taskName: 'TASK-001',
            description: 'create test table',
            migrationDate: '2025-01-11',
            type: 'sql',
            filePath: '/test/001_up.sql',
            code: 'CREATE TABLE test_table (id INTEGER PRIMARY KEY, name TEXT)',
            direction: 'up'
        );

        $operation->setRollbackCode('DROP TABLE test_table');

        $this->repository->save($operation);

        $migration = new Migration('TASK-001', 'Test migration');
        $migration->addMigrationOperation($operation);

        $this->executor->executeMigration($migration);

        // Verify table was created
        $storage = $this->getMainStorage();
        $result = $storage->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='test_table'");
        $this->assertNotNull($result);

        // Verify operation status
        $savedOp = $this->repository->getByTaskName('TASK-001')[0];
        $this->assertInstanceOf(MigrationOperationInterface::class, $savedOp);
    }

    public function testExecuteMigrationWithRollbackOnFailure(): void
    {
        $op1 = new FileMigrationOperation(
            version: 2,
            taskName: 'TASK-002',
            description: 'create table 1',
            migrationDate: '2025-01-11',
            type: 'sql',
            filePath: '/test/002_1_up.sql',
            code: 'CREATE TABLE table1 (id INTEGER PRIMARY KEY)',
            direction: 'up'
        );
        $op1->setRollbackCode('DROP TABLE table1');

        $op2 = new FileMigrationOperation(
            version: 3,
            taskName: 'TASK-002',
            description: 'failing operation',
            migrationDate: '2025-01-11',
            type: 'sql',
            filePath: '/test/002_2_up.sql',
            code: 'INVALID SQL SYNTAX HERE',
            direction: 'up'
        );
        $op2->setRollbackCode('');

        $this->repository->save($op1);
        $this->repository->save($op2);

        $migration = new Migration('TASK-002', 'Test migration with failure');
        $migration->addMigrationOperation($op1);
        $migration->addMigrationOperation($op2);

        $exceptionThrown = false;
        try {
            $this->executor->executeMigration($migration);
        } catch (\Throwable $e) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown, 'Exception should be thrown');

        // Verify first table was rolled back
        $storage = $this->getMainStorage();
        $result = $storage->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='table1'");
        $this->assertNull($result, 'table1 should be rolled back');
    }

    public function testApplyMigrationWithoutRollback(): void
    {
        $op1 = new FileMigrationOperation(
            version: 4,
            taskName: 'TASK-003',
            description: 'create table',
            migrationDate: '2025-01-11',
            type: 'sql',
            filePath: '/test/003_1_up.sql',
            code: 'CREATE TABLE table2 (id INTEGER PRIMARY KEY)',
            direction: 'up'
        );

        $op2 = new FileMigrationOperation(
            version: 5,
            taskName: 'TASK-003',
            description: 'failing operation',
            migrationDate: '2025-01-11',
            type: 'sql',
            filePath: '/test/003_2_up.sql',
            code: 'INVALID SQL',
            direction: 'up'
        );

        $this->repository->save($op1);
        $this->repository->save($op2);

        $migration = new Migration('TASK-003', 'Test apply without rollback');
        $migration->addMigrationOperation($op1);
        $migration->addMigrationOperation($op2);

        $exceptionThrown = false;
        try {
            $this->executor->applyMigration($migration);
        } catch (\Throwable $e) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown, 'Exception should be thrown');

        // Verify first table was NOT rolled back (remains created)
        $storage = $this->getMainStorage();
        $result = $storage->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='table2'");
        $this->assertNotNull($result, 'table2 should remain after applyMigration failure');
    }
}
