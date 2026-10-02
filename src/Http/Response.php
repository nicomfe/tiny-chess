<?php

declare(strict_types=1);

namespace Chess\Http;

final class Response
{
    /**
     * @param array<string, string> $headers
     * @param list<Cookie>          $cookies
     */
    private function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
        public readonly array $cookies = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=utf-8'], $body);
    }

    /** @param array<string, mixed> $payload */
    public static function json(array $payload, int $status = 200): self
    {
        return new self(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self($status, ['Location' => $location], '');
    }

    public function withCookie(Cookie $cookie): self
    {
        return new self($this->status, $this->headers, $this->body, [...$this->cookies, $cookie]);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        foreach ($this->cookies as $cookie) {
            header('Set-Cookie: ' . $cookie->headerValue(), false);
        }

        echo $this->body;
    }
}
