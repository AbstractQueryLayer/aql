<?php

declare(strict_types=1);

namespace IfCastle\AQL\MigrationTool;

use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\AQL\Entity\Property\PropertyDateTime;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\Entity\Property\PropertyString;
use IfCastle\AQL\Entity\Property\PropertyText;
use IfCastle\AQL\Aspects\Storage\PrimaryKey;

class MigrationEntity extends EntityAbstract
{
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_RUNNING = 'running';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_FAILED = 'failed';
    public const string STATUS_ROLLBACK = 'rollback';

    public const string TYPE_SQL = 'sql';
    public const string TYPE_PHP = 'php';

    #[\Override]
    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey(PrimaryKey::BIG_INT));
    }

    /**
     * @throws EntityDescriptorException
     */
    #[\Override]
    protected function buildProperties(): void
    {
        $this->describeProperty(new PropertyInteger('version'))
            ->describeProperty(new PropertyString('taskName', maxLength: 255))
            ->describeProperty(new PropertyString('description', maxLength: 500))
            ->describeProperty(new PropertyString('migrationDate', maxLength: 10))
            ->describeProperty(new PropertyString('type', maxLength: 10))
            ->describeProperty(new PropertyString('filePath', maxLength: 1000))
            ->describeProperty(new PropertyText('code'))
            ->describeProperty(new PropertyText('rollbackCode'))
            ->describeProperty(new PropertyString('checksum', maxLength: 64))
            ->describeProperty(new PropertyString('status', maxLength: 20))
            ->describeProperty(new PropertyDateTime('startedAt'))
            ->describeProperty(new PropertyDateTime('completedAt'));
    }
}
