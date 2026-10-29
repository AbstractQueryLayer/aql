<?php

declare(strict_types=1);

namespace IfCastle\AQL\OpenSearch;

use IfCastle\AQL\Dsl\Parser\AqlParser;
use IfCastle\AQL\OpenSearch\Storage\MatchPolicy;
use IfCastle\AQL\OpenSearch\Storage\SearchRequest;
use IfCastle\AQL\OpenSearch\Storage\SearchRequestRenderer;
use IfCastle\AQL\Storage\Exceptions\StorageException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The renderer on parsed queries: columns keep their names, as an entity without field mapping leaves them. */
final class SearchRequestRendererTest extends TestCase
{
    public function testFullTextFunctionsFiltersCollapseAndLimit(): void
    {
        $request = self::render('SELECT id, workId AS work FROM Fragment WHERE fragmentCount > 0'
            . ' AND PHRASE(text, {phrase}) AND MATCH_PREFIX(text, title, {words}) AND tagIds = {tag}'
            . ' AND tagIds IN ({tags}) AND ratingId != 3 GROUP BY workId LIMIT 50',
            ['phrase' => 'старую дверь', 'words' => 'тёмная загад', 'tag' => 1, 'tags' => [2, 3]]);

        $words = ['query' => 'тёмная загад', 'operator' => 'and'];
        $this->assertSame('Fragment', $request->index);
        $this->assertSame(['id' => 'id', 'workId' => 'work'], $request->columns);
        $this->assertFalse($request->everyRow);
        $this->assertSame([
            '_source' => ['id', 'workId'],
            'track_total_hits' => false,
            'query' => ['bool' => [
                'filter' => [
                    ['range' => ['fragmentCount' => ['gt' => 0]]],
                    ['term' => ['tagIds' => 1]],
                    ['terms' => ['tagIds' => [2, 3]]],
                ],
                'must' => [
                    ['match_phrase' => ['text.exact' => 'старую дверь']],
                    ['bool' => ['should' => [
                        ['multi_match' => $words + ['fields' => ['text', 'title']]],
                        ['multi_match' => $words + ['fuzziness' => 'AUTO:5,9',
                            'fields' => ['text.exact', 'title.exact']]],
                        ['multi_match' => ['query' => 'тёмная загад', 'type' => 'bool_prefix', 'operator' => 'and',
                            'fuzziness' => 'AUTO:5,9', 'fields' => ['text.exact', 'title.exact']]],
                    ], 'minimum_should_match' => 1]],
                ],
                'must_not' => [['term' => ['ratingId' => 3]]],
            ]],
            'collapse' => ['field' => 'workId'],
            'size' => 50,
        ], $request->body);
    }

    public function testASelectWithoutLimitReadsEveryRowInSortOrder(): void
    {
        $request = self::render('SELECT id FROM Fragment WHERE chapterId = {c} ORDER BY id', ['c' => 10]);

        $this->assertTrue($request->everyRow);
        $this->assertSame([['id' => 'asc']], $request->body['sort']);
        $this->assertSame(SearchRequestRenderer::PAGE, $request->body['size']);
    }

    public function testAnEmptyTextAddsNoClause(): void
    {
        $request = self::render('SELECT id FROM Fragment WHERE MATCH(text, {q}) LIMIT 5', ['q' => ' ']);

        $this->assertEquals(['match_all' => (object) []], $request->body['query']);
    }

    public static function refused(): array
    {
        return [
            'OR' => ['SELECT id FROM Fragment WHERE (workId = 1 OR workId = 2) LIMIT 5'],
            'another function' => ['SELECT id FROM Fragment WHERE LOWER(text) = {q} LIMIT 5'],
            'no LIMIT, no ORDER BY' => ['SELECT id FROM Fragment WHERE workId = 1'],
            'no LIMIT with GROUP BY' => ['SELECT id FROM Fragment GROUP BY workId ORDER BY id'],
            'PHRASE over two columns' => ['SELECT id FROM Fragment WHERE PHRASE(text, title, {q}) LIMIT 5'],
            'LIKE' => ['SELECT id FROM Fragment WHERE text LIKE {q} LIMIT 5'],
            'DISTINCT' => ['SELECT DISTINCT workId FROM Fragment ORDER BY workId LIMIT 5'],
        ];
    }

    #[DataProvider('refused')]
    public function testAConstructTheSearchApiCannotExpressIsRefused(string $aql): void
    {
        $this->expectException(StorageException::class);
        self::render($aql, ['q' => 'x']);
    }

    private static function render(string $aql, array $parameters): SearchRequest
    {
        $query = (new AqlParser())->parse($aql);
        $query->applyParameters($parameters);

        return (new SearchRequestRenderer(new MatchPolicy()))->render($query);
    }
}
