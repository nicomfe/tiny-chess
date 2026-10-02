<?php

declare(strict_types=1);

namespace Chess\Game;

/**
 * Links default to the host the request arrived on, so the same code produces
 * working links on localhost and on the deployed domain. APP_BASE_URL wins
 * when set, for setups behind a proxy that rewrites the host.
 */
final class MatchLinkFactory
{
    public function __construct(private readonly ?string $baseUrlOverride)
    {
    }

    public function forRequestBaseUrl(string $requestBaseUrl): MatchLinks
    {
        return new MatchLinks($this->baseUrlOverride ?? $requestBaseUrl);
    }
}
