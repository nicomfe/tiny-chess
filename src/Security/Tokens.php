<?php

declare(strict_types=1);

namespace Chess\Security;

/**
 * Issues and verifies the player tokens that stand in for accounts.
 *
 * Tokens carry 256 bits of entropy, so a keyed SHA-256 digest is enough at
 * rest: there is nothing to brute force, and verification stays cheap enough
 * for once-a-second polling. The raw token never leaves the player's URL or
 * cookie.
 */
final class Tokens
{
    public function __construct(private readonly string $secret)
    {
    }

    public function issue(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function hash(string $token): string
    {
        return hash_hmac('sha256', $token, $this->secret);
    }

    public function verify(string $token, string $expectedHash): bool
    {
        return hash_equals($expectedHash, $this->hash($token));
    }
}
