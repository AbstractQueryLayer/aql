<?php

declare(strict_types=1);

namespace IfCastle\AQL\OpenSearch\Storage;

use IfCastle\AQL\Dsl\Node\NodeInterface;
use IfCastle\AQL\Dsl\Sql\Column\ColumnInterface;
use IfCastle\AQL\Dsl\Sql\Conditions\ConditionsInterface;
use IfCastle\AQL\Dsl\Sql\Constant\ConstantInterface;
use IfCastle\AQL\Dsl\Sql\FunctionReference\FunctionReferenceInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\InExpression;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\LROperationInterface;
use IfCastle\AQL\Dsl\Sql\Query\Expression\Operation\SingleOperation;
use IfCastle\AQL\Dsl\Sql\Query\Expression\OrderItemInterface;
use IfCastle\AQL\Dsl\Sql\Query\QueryInterface;
use IfCastle\AQL\Dsl\Sql\Query\SelectInterface;
use IfCastle\AQL\Storage\Exceptions\StorageException;

/**
 * Renders a transformed AQL SELECT on one entity to an OpenSearch `_search` request.
 *
 * The mapping, clause by clause: the tuple names the `_source` fields; the full-text functions (MATCH, MATCH_PREFIX,
 * PHRASE) are scored `must` clauses, every other condition of the AND is a `filter` (`=` term, `IN` terms, `!=` and
 * `NOT IN` negated, comparisons ranges); `GROUP BY` one column collapses on it, so each group gives its best hit;
 * `ORDER BY` sorts, and without it hits come in relevance order; `LIMIT` is the hit count. A SELECT without LIMIT reads
 * every matching document page by page, so it needs an ORDER BY on a column whose values are unique.
 * Anything else (joins, OR, functions outside the three, subqueries) is refused rather than approximated.
 */
final readonly class SearchRequestRenderer
{
    public const string MATCH = 'MATCH';
    public const string MATCH_PREFIX = 'MATCH_PREFIX';
    public const string PHRASE = 'PHRASE';
    /** Documents per request when a SELECT without LIMIT reads every match. */
    public const int PAGE = 1000;

    public function __construct(private MatchPolicy $policy) {}

    /** True for a function this renderer turns into a full-text clause. */
    public static function isTextFunction(string $name): bool
    {
        return \in_array(\strtoupper($name), [self::MATCH, self::MATCH_PREFIX, self::PHRASE], true);
    }

    /**
     * @throws StorageException when the query uses a construct the search API cannot express
     */
    public function render(QueryInterface $query): SearchRequest
    {
        $from = $query->getFrom() ?? throw self::refuse('a SELECT without FROM', $query);
        if ($from->getChildJoins() !== []) {
            throw self::refuse('JOIN', $query);
        }

        // A search request has no DISTINCT: refused rather than read with the repeated rows.
        if ($query instanceof SelectInterface && $query->isDistinct()) {
            throw self::refuse('DISTINCT', $query);
        }

        $subject = $from->getSubject();
        $index = $subject->getResolvedName() ?: $subject->getSubjectName();
        [$fields, $aliases] = $this->tuple($query);

        $bool = [];
        if ($query->getWhere() !== null) {
            $this->condition($query->getWhere(), $bool, $query);
        }

        $body = ['_source' => $fields, 'track_total_hits' => false,
            'query' => $bool === [] ? ['match_all' => (object) []] : ['bool' => $bool]];

        $groups = \array_values($query->getGroupBy()?->getChildNodes() ?? []);
        if (\count($groups) > 1) {
            throw self::refuse('GROUP BY more than one column', $query);
        }

        if ($groups !== []) {
            $body['collapse'] = ['field' => $this->field($groups[0], $query)];
        }

        foreach ($query->getOrderBy()?->getChildNodes() ?? [] as $item) {
            $item = $item->resolveNode();
            if (!$item instanceof OrderItemInterface) {
                throw self::refuse('this ORDER BY item', $query);
            }

            $column = \array_values($item->getChildNodes())[0];
            $body['sort'][] = [$this->field($column, $query) => \strtolower($item->getDirection())];
        }

        $limit = $query->getLimit()?->resolveNode();
        if ($limit !== null && $limit->isNotEmpty()) {
            $body['size'] = $limit->getLimit();
            if ($limit->getOffset() > 0) {
                $body['from'] = $limit->getOffset();
            }

            return new SearchRequest($index, $body, $aliases, false);
        }

        if (!isset($body['sort']) || isset($body['collapse'])) {
            throw self::refuse('a SELECT without LIMIT needs ORDER BY a unique column and no GROUP BY', $query);
        }

        $body['size'] = self::PAGE;

        return new SearchRequest($index, $body, $aliases, true);
    }

    /** @return array{list<string>, array<string, string>} the source fields and the row key of each */
    private function tuple(QueryInterface $query): array
    {
        $fields = [];
        $aliases = [];
        foreach ($query->getTuple()?->getTupleColumns() ?? [] as $column) {
            $field = $this->field($column->getExpression(), $query);
            $fields[] = $field;
            $aliases[$field] = $column->getAliasOrColumnName();
        }

        if ($fields === []) {
            throw self::refuse('a SELECT without columns', $query);
        }

        return [$fields, $aliases];
    }

    /**
     * Adds $node to the bool query: an AND of conditions, a full-text function or a comparison of a column with a
     * value.
     */
    private function condition(NodeInterface $node, array &$bool, QueryInterface $query): void
    {
        $node = $node->resolveNode();
        if ($node instanceof ConditionsInterface) {
            if ($node->getType() !== ConditionsInterface::TYPE_AND) {
                throw self::refuse($node->getType(), $query);
            }

            foreach ($node->getChildNodes() as $child) {
                $this->condition($child, $bool, $query);
            }

            return;
        }

        if ($node instanceof SingleOperation) {
            $this->condition($node->getExpression(), $bool, $query);

            return;
        }

        if ($node instanceof FunctionReferenceInterface && self::isTextFunction($node->getFunctionName())) {
            $clause = $this->textClause($node, $query);
            if ($clause !== null) {
                $bool['must'][] = $clause;
            }

            return;
        }

        if (!$node instanceof LROperationInterface) {
            throw self::refuse($node->getAql(), $query);
        }

        $field = $this->field($node->getLeftNode(), $query);
        $value = $this->value($node->getRightNode(), $query);
        match ($node->getOperation()) {
            LROperationInterface::EQU => $bool['filter'][] = ['term' => [$field => $value]],
            LROperationInterface::NOT_EQU, '<>' => $bool['must_not'][] = ['term' => [$field => $value]],
            LROperationInterface::IN => $bool['filter'][] = ['terms' => [$field => $value]],
            LROperationInterface::NOT_IN => $bool['must_not'][] = ['terms' => [$field => $value]],
            LROperationInterface::GREATER => $bool['filter'][] = ['range' => [$field => ['gt' => $value]]],
            LROperationInterface::GREATER_EQU => $bool['filter'][] = ['range' => [$field => ['gte' => $value]]],
            LROperationInterface::LESS => $bool['filter'][] = ['range' => [$field => ['lt' => $value]]],
            LROperationInterface::LESS_EQU => $bool['filter'][] = ['range' => [$field => ['lte' => $value]]],
            default => throw self::refuse('the operation ' . $node->getOperation(), $query),
        };
    }

    /**
     * MATCH(fields…, words) and MATCH_PREFIX(fields…, words): every word required, stemmed in any field or within
     * the typo budget in its exact subfield; MATCH_PREFIX also takes the last word as a prefix. PHRASE(field, text):
     * the exact word sequence. An empty text adds no clause.
     */
    private function textClause(FunctionReferenceInterface $function, QueryInterface $query): ?array
    {
        $arguments = $function->getFunctionParameters();
        $text = \count($arguments) < 2 ? null : $this->value(\array_pop($arguments), $query);
        if (!\is_string($text)) {
            throw self::refuse($function->getFunctionName() . ' without columns and a text', $query);
        }

        $fields = \array_map(fn(NodeInterface $node): string => $this->field($node, $query), $arguments);
        $exact = \array_map($this->policy->exact(...), $fields);
        if (\trim($text) === '') {
            return null;
        }

        $name = \strtoupper($function->getFunctionName());
        if ($name === self::PHRASE) {
            if (\count($fields) !== 1) {
                throw self::refuse('PHRASE over more than one column', $query);
            }

            return ['match_phrase' => [$exact[0] => $text]];
        }

        $should = [
            ['multi_match' => ['query' => $text, 'operator' => 'and', 'fields' => $fields]],
            ['multi_match' => ['query' => $text, 'operator' => 'and', 'fuzziness' => $this->policy->fuzziness,
                'fields' => $exact]],
        ];
        if ($name === self::MATCH_PREFIX) {
            $should[] = ['multi_match' => ['query' => $text, 'type' => 'bool_prefix', 'operator' => 'and',
                'fuzziness' => $this->policy->fuzziness, 'fields' => $exact]];
        }

        return ['bool' => ['should' => $should, 'minimum_should_match' => 1]];
    }

    private function field(NodeInterface $node, QueryInterface $query): string
    {
        $node = $node->resolveNode();
        if (!$node instanceof ColumnInterface) {
            throw self::refuse('the expression ' . $node->getAql() . ' where a column is expected', $query);
        }

        return $node->getFieldName() ?: $node->getColumnName();
    }

    /** The value of a constant or a parameter; a list for an IN list. */
    private function value(NodeInterface $node, QueryInterface $query): mixed
    {
        $node = $node->resolveNode();
        if ($node instanceof InExpression) {
            $values = [];
            foreach ($node->getChildNodes() as $item) {
                $value = $this->value($item, $query);
                \array_push($values, ...(\is_array($value) ? \array_values($value) : [$value]));
            }

            return $values;
        }

        if (!$node instanceof ConstantInterface) {
            throw self::refuse('the expression ' . $node->getAql() . ' where a value is expected', $query);
        }

        return $node->getConstantValue();
    }

    private static function refuse(string $construct, QueryInterface $query): StorageException
    {
        return new StorageException([
            'template' => 'The OpenSearch storage cannot express {construct} in {query}',
            'construct' => $construct,
            'query' => $query->getAql(),
        ]);
    }
}
