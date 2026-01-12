<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool\MigrationByDiff;

use IfCastle\AQL\Dsl\Ddl\TableInterface;
use IfCastle\AQL\MigrationTool\MigrationInterface;

interface MigrationTableGeneratorByDiffInterface
{
    public function generateTableMigrationByDiff(TableInterface|null $newTable, TableInterface|null $currentTable): MigrationInterface;
}
