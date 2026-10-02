<?php

declare(strict_types=1);

namespace Chess\Http;

/**
 * A cookie to set on the response.
 *
 * Every cookie this app sets carries a secret the browser never needs to read,
 * so `HttpOnly` and `SameSite=Lax` are not options but fixed properties. Lax
 * still travels on a top-level navigation, which is exactly how a shared play
 * link gets opened. The path is the whole site because the same identity is
 * needed on both the game page and the API.
 */
final class Cookie
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
        public readonly int $maxAgeSeconds,
        public readonly bool $secure,
    ) {
    }

    public function headerValue(): string
    {
        $attributes = [
            $this->name . '=' . $this->value,
            'Max-Age=' . $this->maxAgeSeconds,
            'Path=/',
            'HttpOnly',
            'SameSite=Lax',
        ];

        if ($this->secure) {
            $attributes[] = 'Secure';
        }

        return implode('; ', $attributes);
    }
}
