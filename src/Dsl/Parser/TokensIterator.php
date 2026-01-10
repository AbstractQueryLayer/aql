<?php

declare(strict_types=1);

namespace IfCastle\AQL\Dsl\Parser;

use IfCastle\AQL\Dsl\Parser\Exceptions\ParseException;

class TokensIterator extends \ArrayIterator implements TokensIteratorInterface
{
    protected static array $knowsKeys       = [
        T_FUNCTION, T_AS, T_ARRAY, T_BREAK, T_ABSTRACT,
        T_CONST, T_CONTINUE, T_DEFAULT, T_DECLARE, T_DO, T_WHILE,
        T_ECHO, T_EMPTY, T_EXIT, T_EXTENDS, T_FINAL, T_FINALLY,
        T_GLOBAL, T_GOTO, T_INTERFACE, T_NEW, T_NAMESPACE,
        T_PRIVATE, T_PUBLIC, T_PROTECTED, T_RETURN,
    ];

    protected static array $ignoredTokens   = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    protected static array $wrongTokens     = [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG];

    protected array $stopTokens     = [];

    protected bool $passIgnoreTokens = false;

    protected int $recursionDepth = 0;

    /**
     * @var array<string, string|bool|null>
     */
    protected array $options        = [];

    public function __construct(string $code, protected readonly int $maxRecursionDepth = 0)
    {
        // Parse tokens
        $tokens                     = \token_get_all('<?php ' . $code);
        // and remove first
        \array_shift($tokens);

        parent::__construct($tokens);
    }

    #[\Override]
    public function getStopTokens(): array
    {
        return $this->stopTokens;
    }

    #[\Override]
    public function setStopTokens(array $stopTokens): static
    {
        return $this->clearStopTokens()->addStopTokens($stopTokens);
    }

    #[\Override]
    public function addStopTokens(array $stopTokens): static
    {
        foreach ($stopTokens as $key => $token) {

            if (\is_string($key) && \is_bool($token)) {
                $this->stopTokens[\strtolower($key)] = $token;
            } else {
                $this->stopTokens[\strtolower((string) $token)] = true;
            }
        }

        return $this;
    }

    #[\Override]
    public function clearStopTokens(): static
    {
        $this->stopTokens            = [];
        return $this;
    }

    #[\Override]
    public function nextTokens(): static
    {
        $this->nextToken();
        return $this;
    }

    /**
     * @throws ParseException
     */
    #[\Override]
    public function currentToken(): array
    {
        if (false === $this->passIgnoreTokens) {
            $this->passIgnoreTokens = true;

            while ($this->valid() && \is_array($this->current()) && \in_array($this->current()[0], self::$ignoredTokens)) {
                $this->next();
            }
        }

        if (false === $this->valid()) {
            return [false, '', false];
        }

        $token                      = $this->current();

        // Normalize delimiters: ,)(...
        if (!\is_array($token)) {
            return [0, $token ?? '', 0];
        }

        if (\in_array($token[0], self::$wrongTokens, true)) {
            throw new ParseException('Invalid token "{token}"', ['token' => $token[1]]);
        }

        // normalize type to T_STRING
        if (\in_array($token[0], self::$knowsKeys, true)) {
            $token[0]               = T_STRING;
        }

        return $token;
    }

    /**
     * @throws ParseException
     */
    #[\Override]
    public function currentTokenAsString(bool $isLowCase = false): string
    {
        return $isLowCase ? \strtolower((string) $this->currentToken()[1]) : \strtoupper((string) $this->currentToken()[1]);
    }

    /**
     * @throws ParseException
     */
    #[\Override]
    public function assertTokenIs(string $token): static
    {
        if ($this->currentTokenAsString() !== \strtoupper($token)) {
            throw new ParseException(
                \sprintf('Expected token \'%s\', got \'%s\'', $token, $this->currentTokenAsString()),
                ['line' => $this->getCurrentLine()],
            );
        }

        $this->nextTokens();

        return $this;
    }

    /**
     * @throws ParseException
     */
    #[\Override]
    public function assertTokens(bool $isRequired, string ...$tokens): bool
    {
        $started                    = false;

        foreach ($tokens as $token) {
            if ($this->currentTokenAsString() !== \strtoupper($token)) {

                if ($isRequired || $started) {
                    throw new ParseException(
                        \sprintf('Expected token \'%s\' for \'%s\', got \'%s\'', $token, \implode(' ', $tokens), $this->currentTokenAsString()),
                        ['line' => $this->getCurrentLine()],
                    );
                }

                return false;

            }

            $started                = true;
            $this->nextTokens();
        }

        return true;
    }

    /**
     * Check and consume token if it is equal to the current token.
     *
     * @throws ParseException
     */
    #[\Override]
    public function checkAndConsumeToken(string $token): bool
    {
        if ($this->currentTokenAsString() !== \strtoupper($token)) {
            $this->nextTokens();
            return true;
        }

        return false;
    }

    #[\Override]
    public function nextToken(): array
    {
        if ($this->valid() === false) {
            return [false, '', false];
        }

        do {
            $this->next();
        } while ($this->valid() && \is_array($this->current()) && \in_array($this->current()[0], self::$ignoredTokens));

        $this->passIgnoreTokens     = true;

        return $this->currentToken();
    }

    /**
     * @throws ParseException
     */
    #[\Override]
    public function getCurrentLine(): int
    {
        return $this->currentToken()[2];
    }

    /**
     * @throws ParseException
     */
    #[\Override]
    public function expectKeywords(string $keywords): static
    {
        foreach (\explode(' ', \strtoupper($keywords)) as $item) {

            if ($this->currentTokenAsString() !== $item) {
                throw new ParseException(
                    \sprintf('Expected keyword \'%s\' for \'%s\', got \'%s\'', $item, $keywords, $this->currentTokenAsString()),
                    ['line' => $this->getCurrentLine()],
                );
            }

            $this->nextTokens();
        }

        return $this;
    }

    #[\Override]
    public function getOptions(): array
    {
        return $this->options;
    }

    #[\Override]
    public function isOption(string $option): bool
    {
        return isset($this->options[$option]);
    }

    /**
     * @throws ParseException
     */
    #[\Override]
    public function throwIfOptionEmpty(string $option): void
    {
        if (empty($this->options[$option])) {
            throw new ParseException('The Query with "' . $option . '" option is not allowed', ['line' => $this->getCurrentLine()]);
        }
    }

    /**
     * @throws ParseException
     */
    public function throwIfNotEnded(): void
    {
        if (false === $this->valid()) {
            return;
        }

        throw new ParseException(
            'Unexpected token: "' . $this->currentTokenAsString() .
            '". Expected end of expression.', ['line' => $this->getCurrentLine()],
        );
    }

    #[\Override]
    public function isOptionEqual(string $option, bool|string|null $value): bool
    {
        return \array_key_exists($option, $this->options) && $this->options[$option] === $value;
    }

    #[\Override]
    public function getOption(string $option): string|bool|null
    {
        return $this->options[$option] ?? null;
    }

    #[\Override]
    public function setOption(string $option, bool|string|null $value): static
    {
        $this->options[$option]     = $value;
        return $this;
    }

    #[\Override]
    public function withOptions(string ...$options): static
    {
        foreach ($options as $option) {
            $this->options[$option] = true;
        }

        return $this;
    }

    /**
     * @throws ParseException
     */
    #[\Override]
    public function increaseRecursionDepth(): static
    {
        $this->recursionDepth++;

        if ($this->maxRecursionDepth > 0 && $this->recursionDepth > $this->maxRecursionDepth) {
            throw new ParseException(
                'Recursion depth exceeded: ' . $this->maxRecursionDepth, ['line' => $this->getCurrentLine()],
            );
        }

        return $this;
    }

    #[\Override]
    public function decreaseRecursionDepth(): static
    {
        $this->recursionDepth--;
        return $this;
    }
}
