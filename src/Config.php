<?php

declare(strict_types=1);

namespace Chess;

use RuntimeException;

/**
 * Application configuration, sourced from the process environment with an
 * optional .env file as fallback for local development.
 */
final class Config
{
    /** @param array<string, string> $values */
    private function __construct(private readonly array $values)
    {
    }

    public static function load(string $projectRoot): self
    {
        $values = [];

        $envFile = $projectRoot . '/.env';
        if (is_readable($envFile)) {
            $values = self::parseEnvFile($envFile);
        }

        foreach (self::KNOWN_KEYS as $key) {
            $fromEnvironment = getenv($key);
            if (is_string($fromEnvironment) && $fromEnvironment !== '') {
                $values[$key] = $fromEnvironment;
            }
        }

        return new self($values);
    }

    public function databaseDsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $this->requireValue('DB_HOST'),
            $this->get('DB_PORT', '3306'),
            $this->requireValue('DB_NAME'),
        );
    }

    public function databaseUser(): string
    {
        return $this->requireValue('DB_USER');
    }

    public function databasePassword(): string
    {
        return $this->get('DB_PASSWORD', '');
    }

    /**
     * Secret used to key the token hashes. Required: without it, creator links
     * from a previous boot would stop resolving.
     */
    public function appSecret(): string
    {
        return $this->requireValue('APP_SECRET');
    }

    /** Absolute base URL for generated links, or null to derive it from the request. */
    public function baseUrlOverride(): ?string
    {
        $value = rtrim($this->get('APP_BASE_URL', ''), '/');

        return $value === '' ? null : $value;
    }

    private const KNOWN_KEYS = [
        'APP_SECRET',
        'APP_BASE_URL',
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
    ];

    private function get(string $key, string $default): string
    {
        return $this->values[$key] ?? $default;
    }

    private function requireValue(string $key): string
    {
        $value = $this->values[$key] ?? '';
        if ($value === '') {
            throw new RuntimeException("Missing required configuration: {$key}");
        }

        return $value;
    }

    /** @return array<string, string> */
    private static function parseEnvFile(string $path): array
    {
        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim(trim($value), "\"'");
        }

        return $values;
    }
}
