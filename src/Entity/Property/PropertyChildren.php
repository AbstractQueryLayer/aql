<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

/**
 * ## ChildrenProperty
 * Virtual property that allows you to build a tree structure from the result.
 * Using this virtual property affects the structure of the resulting tuple!
 *
 */
class PropertyChildren extends VirtualProperty
{
    public const NAME                      = 'children';

    public const PARENT_ID                 = '_parent_id';

    public function __construct(string $name = self::NAME, string $type = self::T_LIST)
    {
        parent::__construct($name, $type);
    }

    public function resultModifier(TupleI $dbResult, string $propertyKey, string $hiddenPrimaryKey): void
    {
        $results                = [];

        foreach ($dbResult->toArray() as $row) {
            $primaryKey         = $row[$hiddenPrimaryKey];
            unset($row[$hiddenPrimaryKey]);
            $results[$primaryKey] = $row;
        }

        foreach ($results as &$result) {

            if (!\array_key_exists($propertyKey, $result)) {
                $result[$propertyKey] = [];
            }

            if (!empty($results[$result[self::PARENT_ID]])) {

                $parent             = &$results[$result[self::PARENT_ID]];

                if (!\array_key_exists($propertyKey, $parent)) {
                    $parent[$propertyKey] = [];
                }

                $parent[$propertyKey][] = &$result;

                unset($result[self::PARENT_ID]);
            }
        }

        unset($result, $parent);


        foreach ($results as $id => &$result) {

            if (!\array_key_exists(self::PARENT_ID, $result)) {
                unset($results[$id]);
            } else {
                unset($result[self::PARENT_ID]);
            }
        }

        unset($result);


        $dbResult->modify($results);
    }

    /**
     * @throws \IfCastle\AQL\DataBase\Exceptions\EntityNotFound
     * @throws \IfCastle\AQL\DataBase\Exceptions\ExpressionException
     */
    protected function handleTuple(PropertyContext $context): bool
    {
        $entity                     = $context->getMainEntity();
        $primaryKey                 = $entity->getPrimaryKey();

        $relations                  = $entity->getRelations($entity->getName());

        if ($relations === null) {
            throw new ExpressionException(\sprintf('The property %s can\'t use without self-relations', $this->getName()));
        }

        $fieldRef                   = $relations->getLeftFieldRef();

        $key                        = $context->getColumn()->getAliasOrPropertyName();
        $context->getFieldRef()->setSubstitution($fieldRef);
        $context->getColumn()->setAlias(self::PARENT_ID);

        $hiddenPrimaryKey           = $context->addHiddenResult($primaryKey->getDbName());

        $context->getQuery()->addAfterHandler(new Modifier($this->getName(), function (TupleInterface $result) use ($key, $hiddenPrimaryKey) {
            $this->resultModifier($result, $key, $hiddenPrimaryKey);
        }));

        return true;
    }
}
