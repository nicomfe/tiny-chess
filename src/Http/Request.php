<?php

declare(strict_types=1);

namespace Chess\Http;

final class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, mixed>  $body
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly string $scheme,
        public readonly string $host,
    ) {
    }

    public static function fromGlobals(): self
    {
        $target = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($target, PHP_URL_PATH) ?: '/';

        return new self(
            method: strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            path: '/' . trim($path, '/'),
            query: self::stringMap($_GET),
            body: self::readBody(),
            scheme: self::detectScheme(),
            host: (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'),
        );
    }

    public function queryParam(string $name): ?string
    {
        $value = $this->query[$name] ?? '';

        return $value === '' ? null : $value;
    }

    public function bodyParam(string $name): ?string
    {
        $value = $this->body[$name] ?? null;

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function wantsJson(): bool
    {
        if (str_starts_with($this->path, '/api/')) {
            return true;
        }

        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    public function baseUrl(): string
    {
        return $this->scheme . '://' . $this->host;
    }

    /** @return array<string, mixed> */
    private static function readBody(): array
    {
        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    private static function detectScheme(): string
    {
        if (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off') {
            return 'https';
        }

        if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return 'https';
        }

        return 'http';
    }

    /**
     * @param array<array-key, mixed> $source
     * @return array<string, string>
     */
    private static function stringMap(array $source): array
    {
        $map = [];
        foreach ($source as $key => $value) {
            if (is_string($value)) {
                $map[(string) $key] = $value;
            }
        }

        return $map;
    }
}
