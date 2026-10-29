<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Ddl;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\NodeList;

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

                if (self::isNotBlank($option)) {
                    $tableOptions[] = $option;
                }
            }

            // Table options are one item of the comma-separated ALTER TABLE list.
            if ($tableOptions !== []) {
                $sql[]              = \implode(' ', $tableOptions) . ',';
            }
        }

        $sql[]                      = $this->action;
        $whatKeyword                = $this->whatKeyword();

        if ($whatKeyword !== '') {
            $sql[]                  = $whatKeyword;
        }

        if ($this->getDefinitions()->isNotEmpty()) {

            $definitions            = [];

            foreach ($this->getDefinitions() as $definition) {
                $result             = $definition->getResult();

                if (self::isNotBlank($result)) {
                    $definitions[]  = $result;
                }
            }

            // Only ADD COLUMN holds several definitions, and SQL takes them as one parenthesised list.
            $sql[]                  = \count($definitions) > 1
                                    ? '(' . \implode(', ', $definitions) . ')'
                                    : \implode('', $definitions);
        }

        return \implode(' ', $sql);
    }

    /**
     * The words between the action and the definitions in SQL: an added index definition starts with its own keyword
     * (`ADD UNIQUE KEY ...`, not `ADD INDEX UNIQUE KEY ...`), and a table is renamed by `RENAME TO`.
     */
    protected function whatKeyword(): string
    {
        return match (true) {
            $this->action === AlterActionEnum::ADD->value && $this->what === AlterWhatEnum::INDEX->value => '',
            $this->action === AlterActionEnum::RENAME->value && $this->what === AlterWhatEnum::TABLE->value => 'TO',
            default                 => $this->what,
        };
    }
}
