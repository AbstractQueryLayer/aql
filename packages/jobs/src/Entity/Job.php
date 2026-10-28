<?php

declare(strict_types=1);

namespace IfCastle\Jobs\Entity;

use IfCastle\AQL\Aspects\Storage\PrimaryKey;
use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Property\PropertyBigInteger;
use IfCastle\AQL\Entity\Property\PropertyBinary;
use IfCastle\AQL\Entity\Property\PropertyDateTime;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\Entity\Property\PropertyString;
use IfCastle\AQL\Entity\Property\PropertyText;

/**
 * A background job; workers lease rows with SELECT … FOR UPDATE SKIP LOCKED. The table: resources/mariadb.sql.
 */
final class Job extends EntityAbstract
{
    protected string $dbTable = 'jobs';

    protected function buildAspects(): void
    {
        $this->describeAspect(new PrimaryKey());
    }

    protected function buildProperties(): void
    {
        $this->describeProperty(new PropertyString('kind', maxLength: 64));
        $this->describeProperty(new PropertyText('payload'));
        $this->describeProperty(new PropertyString('subjectKind', isNullable: true, maxLength: 16));
        $this->describeProperty(new PropertyBigInteger('subjectId', isNullable: true));
        $this->describeProperty(new PropertyString('state', maxLength: 16));
        $this->describeProperty(new PropertyInteger('priority'));
        $this->describeProperty(new PropertyDateTime('runAfter'));
        $this->describeProperty(new PropertyInteger('attempts'));
        $this->describeProperty(new PropertyInteger('maxAttempts'));
        $this->describeProperty(new PropertyString('lockedBy', isNullable: true, maxLength: 64));
        $this->describeProperty(new PropertyDateTime('lockedUntil', isNullable: true));
        $this->describeProperty(new PropertyBinary('leaseToken', 16, isNullable: true));
        $this->describeProperty(new PropertyString('dedupKey', isNullable: true, maxLength: 191));
        $this->describeProperty(new PropertyString('lastError', isNullable: true, maxLength: 2000));
        $this->describeProperty(new PropertyDateTime('createdAt'));
        $this->describeProperty(new PropertyDateTime('startedAt', isNullable: true));
        $this->describeProperty(new PropertyDateTime('finishedAt', isNullable: true));
    }
}
