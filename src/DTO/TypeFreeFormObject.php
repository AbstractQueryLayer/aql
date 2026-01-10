<?php

declare(strict_types=1);

namespace IfCastle\AQL\DTO;

use IfCastle\TypeDefinitions\DefinitionAbstract;
use IfCastle\TypeDefinitions\Exceptions\DecodingException;
use IfCastle\TypeDefinitions\Exceptions\EncodingException;
use IfCastle\TypeDefinitions\Value\ValueObject;

final class TypeFreeFormObject extends DefinitionAbstract
{
    public function __construct(string $name, bool $isRequired = true, bool $isNullable = false)
    {
        parent::__construct($name, 'object', $isRequired, $isNullable);
    }

    #[\Override]
    public function isScalar(): bool
    {
        return false;
    }

    #[\Override]
    protected function validateValue(mixed $value): bool
    {
        return $value instanceof ValueObject;
    }

    /**
     * @throws \JsonException
     */
    #[\Override]
    public function decode(array|int|float|string|bool $data): mixed
    {
        if (\is_string($data)) {
            $data                  = \json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        }

        if (\is_array($data)) {
            $instantiableClass      = $this->instantiableClass !== '' ? $this->instantiableClass : ValueObject::class;

            if (!\class_exists($instantiableClass)) {
                throw new DecodingException($this, 'instantiable class not exists', ['value' => $instantiableClass]);
            }

            foreach (\array_keys($data) as $key) {

                if (!\is_string($key)) {
                    throw new DecodingException($this, 'Key should be a string');
                }
            }

            $data                  = $instantiableClass::instantiate($data, $this);
        }

        if ($data instanceof DataTransferObjectInterface === false) {
            throw new DecodingException($this, 'Expected DataTransferObjectI object', ['value' => \get_debug_type($data)]);
        }

        return $data;
    }

    /**
     * @throws EncodingException
     */
    #[\Override]
    public function encode(mixed $data): mixed
    {
        if (!\is_array($data)) {
            throw new EncodingException($this, 'Only array values can be encoded', ['value' => $data]);
        }

        return $data;
    }

    #[\Override]
    protected function buildOpenApiSchema(?callable $definitionHandler = null): array
    {
        return parent::buildOpenApiSchema($definitionHandler) +
            [
                'type'                  => 'object',
                'additionalProperties'  => true,
            ];
    }
}
