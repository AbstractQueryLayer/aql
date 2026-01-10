<?php

declare(strict_types=1);

namespace IfCastle\AQL\Entity\Property;

use IfCastle\AQL\Executor\Exceptions\PropertySerializationException;
use IfCastle\AQL\Executor\Plan\ExecutionContextInterface;

class PropertyJson extends PropertyString
{
    public function __construct(string $name, bool $isNullable = false)
    {
        parent::__construct($name, $isNullable);

        $this->type                 = self::T_JSON;

        $this->isSerializable       = true;
    }

    /**
     * @throws PropertySerializationException
     */
    #[\Override]
    public function propertySerialize($value, ?ExecutionContextInterface $context = null): ?string
    {
        if (\is_string($value)) {
            return $value;
        }

        if (\is_null($value) && false === $this->isNotNull()) {
            return null;
        }

        if (!\is_array($value)) {
            throw new PropertySerializationException($this, true, $value, 'array');
        }

        try {
            $result                 = \json_encode($value, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new PropertySerializationException($this, true, '', $exception->getMessage());
        }

        return $result;
    }

    /**
     * @throws PropertySerializationException
     */
    #[\Override]
    public function propertyUnSerialize($value, ?ExecutionContextInterface $context = null): ?array
    {
        if (\is_array($value)) {
            return $value;
        }

        if (\is_null($value) || $value === '') {
            return $this->isNotNull() ? [] : null;
        }

        if (!\is_string($value)) {
            throw new PropertySerializationException($this, false, $value, 'string');
        }

        $result                     = \json_decode($value, true);

        if (!\is_array($result)) {
            throw new PropertySerializationException($this, false, $value, 'array');
        }

        return $result;
    }
}
