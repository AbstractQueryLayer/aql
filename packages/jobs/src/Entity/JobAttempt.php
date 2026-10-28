<?php

declare(strict_types=1);

namespace IfCastle\Jobs\Entity;

use IfCastle\AQL\Entity\EntityAbstract;
use IfCastle\AQL\Entity\Key\Key;
use IfCastle\AQL\Entity\Property\PropertyDateTime;
use IfCastle\AQL\Entity\Property\PropertyInteger;
use IfCastle\AQL\Entity\Property\PropertyString;

/** One finished attempt of a job, written once. */
final class JobAttempt extends EntityAbstract
{
    protected string $dbTable = 'job_attempts';

    protected function buildAspects(): void {}

    protected function buildProperties(): void
    {
        $this->describeReference(Job::entity());
        $this->describeProperty(new PropertyInteger('attempt'));
        $this->describeProperty(new PropertyString('worker', maxLength: 64));
        $this->describeProperty(new PropertyDateTime('startedAt'));
        $this->describeProperty(new PropertyDateTime('finishedAt'));
        $this->describeProperty(new PropertyString('outcome', maxLength: 16));
        $this->describeProperty(new PropertyString('error', isNullable: true, maxLength: 2000));
        $this->describeKey((new Key('jobId', 'attempt'))->asPrimary());
    }
}
