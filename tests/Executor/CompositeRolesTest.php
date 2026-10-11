<?php

declare(strict_types=1);

namespace IfCastle\AQL\Executor;

use IfCastle\AQL\Dsl\Relation\RelationInterface;
use IfCastle\AQL\Entity\Builder\EntityBuilderInterface;
use IfCastle\AQL\Entity\EntityDescriptorInterface;
use IfCastle\AQL\Entity\EntityInterface;
use IfCastle\AQL\Entity\Exceptions\EntityDescriptorException;
use IfCastle\AQL\Entity\Manager\EntityFactoryInterface;
use IfCastle\AQL\Executor\Entities\RoleCycleLeft;
use IfCastle\AQL\Executor\Entities\SpecialVolume;
use IfCastle\AQL\Executor\Entities\Volume;
use IfCastle\AQL\Executor\Entities\VolumeAlias;
use IfCastle\AQL\Executor\Entities\VolumePage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Roles over column pairs (EntityDescriptorInterface::describeRole): Volume reaches the pages of its published issue
 * through (publishedIssueId, id) = (issueId, volumeId), as works reach edition_chapters in the lib schema.
 */
final class CompositeRolesTest extends SqlQueryExecutorTestCase
{
    public function testCardinalityFollowsTheTargetKeys(): void
    {
        $relations                  = $this->entity(Volume::entity())->getRelations();

        $this->assertSame(RelationInterface::COLLECTION, $relations['publishedPages']->getRelationType());
        $this->assertSame(RelationInterface::REFERENCE, $relations['firstPage']->getRelationType());
        $this->assertSame(['fk_volume_published', 'fk_volume_page_issue'],
            $relations['publishedPages']->getDerivedFrom());
        $this->assertFalse($relations['firstPage']->isRequired());
        $this->assertSame(RelationInterface::REFERENCE,
            $this->entity(VolumeAlias::entity())->getRelations()['volume']->getRelationType());
        $this->assertTrue($relations['publishedPages']->isJoinedByRoleOnly());
    }

    public function testEntityNameLookupDoesNotSeeACompositeRole(): void
    {
        $this->assertNull($this->entity(Volume::entity())->findRelation(VolumePage::entity()));
    }

    public function testAnInheritedCompositeRoleIsStillJoinedByRoleOnly(): void
    {
        $special                    = $this->entity(SpecialVolume::entity());

        $this->assertTrue($special->getRelations()['publishedPages']->isJoinedByRoleOnly());
        $this->assertNull($special->findRelation(VolumePage::entity()));
    }

    public static function roleJoins(): array
    {
        return [
            'to-many role joins every pair' => [
                'SELECT v.name, p.title FROM Volume v LEFT JOIN v.publishedPages p',
                'SELECT `t0`.`name`, `t1`.`title` FROM `volume` as `t0` LEFT JOIN `volume_page` as `t1`
                ON ((`t1`.`issueId`, `t1`.`volumeId`) = (`t0`.`publishedIssueId`, `t0`.`id`))',
            ],
            'to-one role over the whole primary key' => [
                'SELECT p.title FROM Volume v JOIN v.firstPage p WHERE v.id = 1',
                'SELECT `t1`.`title` FROM `volume` as `t0` INNER JOIN `volume_page` as `t1`
                ON ((`t1`.`issueId`, `t1`.`volumeId`, `t1`.`pageNo`)
                = (`t0`.`publishedIssueId`, `t0`.`id`, `t0`.`firstPageNo`)) WHERE `t0`.`id` = \'1\'',
            ],
            'ON of a role join refers to an earlier alias' => [
                'SELECT p.title FROM VolumePage x JOIN x.volume v
                LEFT JOIN v.publishedPages p ON (p.pageNo = x.pageNo)',
                'SELECT `t2`.`title` FROM `volume_page` as `t0`
                INNER JOIN `volume` as `t1` ON (`t1`.`id` = `t0`.`volumeId`)
                LEFT JOIN `volume_page` as `t2`
                ON ((`t2`.`issueId`, `t2`.`volumeId`) = (`t1`.`publishedIssueId`, `t1`.`id`)
                AND `t2`.`pageNo` = `t0`.`pageNo`)',
            ],
            'entity join takes the reference, not the composite role' => [
                'SELECT p.title FROM Volume v JOIN VolumePage p',
                'SELECT `t1`.`title` FROM `volume` as `t0`
                INNER JOIN `volume_page` as `t1` ON (`t1`.`volumeId` = `t0`.`id`)',
            ],
            'inherited composite role runs through the parent table' => [
                'SELECT s.badge, p.title FROM SpecialVolume s LEFT JOIN s.publishedPages p',
                'SELECT `t2`.`badge`, `t1`.`title` FROM `special_volume` as `t2`
                INNER JOIN `volume` as `t0` ON (`t0`.`id` = `t2`.`volumeId`)
                LEFT JOIN `volume_page` as `t1`
                ON ((`t1`.`issueId`, `t1`.`volumeId`) = (`t0`.`publishedIssueId`, `t0`.`id`))',
            ],
        ];
    }

    #[DataProvider('roleJoins')]
    public function testRoleJoin(string $aql, string $sql): void
    {
        $this->executeCase(new SqlQueryCaseDescriptor($aql, $sql));
    }

    public static function rejectedRoles(): array
    {
        $pairs                      = ['publishedIssueId' => 'issueId', 'id' => 'volumeId'];

        return [
            'declared integrity' => ['other', $pairs, RelationInterface::INTEGRITY_DECLARED, 'not supported yet'],
            'no pairs' => ['other', [], RelationInterface::INTEGRITY_LOGICAL, 'no column pairs'],
            'property name' => ['name', $pairs, RelationInterface::INTEGRITY_LOGICAL, 'repeats a property name'],
            'unknown own column' => ['other', ['nope' => 'issueId'], RelationInterface::INTEGRITY_LOGICAL,
                'no column nope'],
            'unknown target column' => ['other', ['id' => 'nope'], RelationInterface::INTEGRITY_LOGICAL,
                'target has no column nope'],
            'same columns as another role' => ['other', $pairs, RelationInterface::INTEGRITY_LOGICAL,
                'publishedPages already joins these columns'],
            'same pairs in another order' => ['other', \array_reverse($pairs, true),
                RelationInterface::INTEGRITY_LOGICAL, 'publishedPages already joins these columns'],
            'name of an entity' => ['VolumePage', $pairs, RelationInterface::INTEGRITY_LOGICAL,
                'begins with a lowercase letter'],
        ];
    }

    /**
     * @param array<string, string> $pairs
     */
    #[DataProvider('rejectedRoles')]
    public function testRejectedRole(string $role, array $pairs, string $integrity, string $message): void
    {
        $volume                     = $this->entity(Volume::entity());
        $this->assertInstanceOf(EntityDescriptorInterface::class, $volume);

        $this->expectException(EntityDescriptorException::class);
        $this->expectExceptionMessage($message);

        $this->getDiContainer()->resolveDependency(EntityBuilderInterface::class)
            ->buildRole($volume, $role, VolumePage::entity(), $pairs, $integrity, []);
    }

    public function testRoleAcrossStoragesIsRejected(): void
    {
        $volume                     = $this->entity(Volume::entity());
        $this->assertInstanceOf(EntityDescriptorInterface::class, $volume);

        $this->expectException(EntityDescriptorException::class);
        $this->expectExceptionMessage('different storages');

        $this->getDiContainer()->resolveDependency(EntityBuilderInterface::class)
            ->buildRole($volume, 'files', 'BookFiles', ['id' => 'id'], RelationInterface::INTEGRITY_LOGICAL, []);
    }

    public function testMutualCompositeRolesFailInsteadOfRecursing(): void
    {
        $this->expectException(EntityDescriptorException::class);
        $this->expectExceptionMessage('RoleCycleLeft -> RoleCycleRight -> RoleCycleLeft');

        $this->entity(RoleCycleLeft::entity());
    }

    private function entity(string $name): EntityInterface
    {
        return $this->getDiContainer()->resolveDependency(EntityFactoryInterface::class)->getEntity($name);
    }
}
