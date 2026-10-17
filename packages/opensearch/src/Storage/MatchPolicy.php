<?php

declare(strict_types=1);

namespace IfCastle\AQL\OpenSearch\Storage;

/**
 * How the full-text functions match words, set once per storage so that queries state only what must match.
 *
 * MATCH requires every word: a word matches its analysed (stemmed) form in any listed field, or the word as written
 * within the typo budget in the field's exact subfield. PHRASE matches the exact subfield as a word sequence.
 */
final readonly class MatchPolicy
{
    /**
     * @param string $fuzziness the typo budget in OpenSearch syntax: `AUTO:5,9` allows no typo below 5 letters, one
     *     from 5 and two from 9
     * @param ?string $exactSubfield the subfield that keeps word forms (`exact` for `text.exact`); null when the
     *     fields have none, and typos and phrases are then matched on the fields themselves
     */
    public function __construct(public string $fuzziness = 'AUTO:5,9', public ?string $exactSubfield = 'exact') {}

    public function exact(string $field): string
    {
        return $this->exactSubfield === null ? $field : $field . '.' . $this->exactSubfield;
    }
}
