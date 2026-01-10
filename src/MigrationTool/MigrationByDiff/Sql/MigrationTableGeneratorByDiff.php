<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\MigrationByDiff\Sql;

use IfCastle\AQL\Dsl\Ddl\AlterActionEnum;
use IfCastle\AQL\Dsl\Ddl\AlterOption;
use IfCastle\AQL\Dsl\Ddl\AlterTable;
use IfCastle\AQL\Dsl\Ddl\AlterWhatEnum;
use IfCastle\AQL\Dsl\Ddl\NameDefinition;
use IfCastle\AQL\Dsl\Ddl\TableInterface;
use IfCastle\AQL\MigrationTool\Exceptions\MigrationException;
use IfCastle\AQL\MigrationTool\Migration;
use IfCastle\AQL\MigrationTool\MigrationByDiff\MigrationTableGeneratorByDiffInterface;
use IfCastle\AQL\MigrationTool\MigrationInterface;

class MigrationTableGeneratorByDiff implements MigrationTableGeneratorByDiffInterface
{
    #[\Override]
    public function generateTableMigrationByDiff(
        TableInterface|null $newTable,
        TableInterface|null $currentTable
    ): MigrationInterface
    {
        if($newTable === null && $currentTable === null) {
            throw new MigrationException('Both tables are null. Cannot generate migration.');
        }
        
        if($newTable !== null && $currentTable === null) {
            return $this->generateTableCreateMigration($newTable);
        }
        
        if($newTable === null && $currentTable !== null) {
            return $this->generateTableDropMigration($currentTable);
        }
        
        return $this->generateTableAlterMigration($newTable, $currentTable);
    }
    
    protected function generateTableCreateMigration(TableInterface $newTable): MigrationInterface
    {
    }
    
    protected function generateTableDropMigration(TableInterface $currentTable): MigrationInterface
    {
    }
    
    protected function generateTableAlterMigration(TableInterface $newTable, TableInterface $currentTable): MigrationInterface
    {
        $migration                  = new Migration(
            'Migration of table '.$currentTable->getTableName().' changes from  ' . date('Y-m-d H:i:s T'),
        );
        
        if($newTable->getTableName() !== $currentTable->getTableName()) {
            // ALTER TABLE old_table_name RENAME TO new_table_name;
            $alterTable = new AlterTable($currentTable->getTableName());
            $alterTable->addAlterOption(
                new AlterOption(AlterWhatEnum::TABLE->value, AlterActionEnum::RENAME->value, new NameDefinition($newTable->getTableName()))
            );
            
            $migration->addMigrationOperation($alterTable);
        }
        
        
        
        
        return $migration;
    }
}
