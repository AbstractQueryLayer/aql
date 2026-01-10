<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\NodeList;
use IfCastle\TypeDefinitions\NativeSerialization\ArraySerializableValidatorInterface;
use IfCastle\TypeDefinitions\NativeSerialization\ArrayTyped;

class AlterOption extends DdlStatementAbstract implements AlterOptionInterface
{
    /**
     * @param NodeInterface[] $definitions
     */
    public function __construct(protected string $what, protected string $action, NodeInterface ...$definitions)
    {
        parent::__construct();

        $this->childNodes[self::TABLE_OPTIONS]  = new NodeList();
        $this->childNodes[self::DEFINITIONS]    = new NodeList(...$definitions);
    }

    #[\Override]
    public static function fromArray(array $array, ?ArraySerializableValidatorInterface $validator = null): static
    {
        return new self(
            $array[self::WHAT] ?? '',
            $array[self::ACTION] ?? '',
            ...ArrayTyped::unserialize($array[self::DEFINITIONS] ?? null, $validator)
        );
    }

    #[\Override]
    public function toArray(?ArraySerializableValidatorInterface $validator = null): array
    {
        return [
            self::WHAT        => $this->what,
            self::ACTION      => $this->action,
            self::DEFINITIONS => ArrayTyped::serializeList($validator, ...$this->childNodes),
        ];
    }

    public function getWhat(): string
    {
        return $this->what;
    }

    /**
     * @return $this
     */
    public function setWhat(string $what): static
    {
        $this->what                 = $what;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @return $this
     */
    public function setAction(string $action): static
    {
        $this->action               = $action;
        return $this;
    }

    /**
     * @return NodeList<NodeInterface>
     */
    public function getDefinitions(): NodeList
    {
        return $this->childNodes[self::DEFINITIONS];
    }

    /**
     * @return NodeList<NodeInterface>
     */
    public function getTableOptions(): NodeList
    {
        return $this->childNodes[self::TABLE_OPTIONS];
    }

    public function setTableOptions(array $tableOptions): static
    {
        $this->childNodes[self::TABLE_OPTIONS] = new NodeList(...$tableOptions);
        return $this;
    }

    #[\Override]
    protected function generateResult(): string
    {
        $sql                        = [];

        if ($this->getTableOptions()->isNotEmpty()) {
            $tableOptions           = [];

            foreach ($this->getTableOptions() as $tableOption) {
                $option             = $tableOption->getResult();

                if (!empty($option)) {
                    $tableOptions[] = $option;
                }
            }

            if ($tableOptions !== []) {
                $sql[]              = \implode(' ', $tableOptions);
            }
        }

        $sql[]                      = $this->action;
        $sql[]                      = $this->what;

        if ($this->getDefinitions()->isNotEmpty()) {

            $definitions            = [];

            foreach ($this->getDefinitions() as $definition) {
                $result             = $definition->getResult();

                if (!empty($result)) {
                    $definitions[]  = $result;
                }
            }

            $sql[]                  = \implode(",\n", $definitions);
        }

        return \implode(' ', $sql);
    }
}
