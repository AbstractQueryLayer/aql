<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;

interface TokensIteratorInterface
{
    public function getStopTokens(): array;

    public function setStopTokens(array $stopTokens): static;

    public function addStopTokens(array $stopTokens): static;

    public function clearStopTokens(): static;

    public function nextTokens(): static;

    /**
     * @return array{int, string, int}|array{int, string}
     */
    public function currentToken(): array;

    public function currentTokenAsString(bool $isLowCase = false): string;

    public function assertTokenIs(string $token): static;

    public function assertTokens(bool $isRequired, string ...$tokens): bool;

    public function checkAndConsumeToken(string $token): bool;

    public function nextToken(): array;

    public function getCurrentLine(): int;

    public function expectKeywords(string $keywords): static;

    public function getOptions(): array;

    public function isOption(string $option): bool;

    /**
     * @throws ParseException
     */
    public function throwIfOptionEmpty(string $option): void;

    /**
     * @throws ParseException
     */
    public function throwIfNotEnded(): void;

    public function isOptionEqual(string $option, string|bool|null $value): bool;

    public function getOption(string $option): string|bool|null;

    public function setOption(string $option, string|bool|null $value): static;

    public function withOptions(string...$options): static;

    public function increaseRecursionDepth(): static;

    public function decreaseRecursionDepth(): static;
}
