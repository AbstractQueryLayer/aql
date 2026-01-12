<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl;

use IfCastle\AQL\Dsl\Node\NodeAbstract;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;

class QueryOption extends NodeAbstract implements QueryOptionInterface
{
    public function __construct(protected string $optionName, protected string|int|float|bool $optionValue = true, protected bool $isOptionHidden = false)
    {
        parent::__construct();
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        return new self($array[self::OPTION_NAME] ?? '', $array[self::OPTION_VALUE] ?? null, $array[self::OPTION_HIDDEN] ?? false);
    }

    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        return [
            self::OPTION_NAME       => $this->optionName,
            self::OPTION_VALUE      => $this->optionValue,
            self::OPTION_HIDDEN     => $this->isOptionHidden,
        ];
    }

    #[\Override]
    public function getOptionName(): string
    {
        return $this->optionName;
    }

    #[\Override]
    public function getOptionValue(): string|int|float|bool
    {
        return $this->optionValue;
    }

    #[\Override]
    public function getAql(bool $forResolved = false): string
    {
        if ($this->optionValue === true) {
            return $this->optionName;
        }

        return \sprintf('[%s=%s]', $this->optionName, $this->optionValue);
    }
}
