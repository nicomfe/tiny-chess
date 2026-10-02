<?php

declare(strict_types=1);

namespace Chess\Http;

/**
 * Minimal path router. Patterns may contain `{name}` placeholders, which are
 * passed to the handler as a map of captured values.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, names: list<string>, handler: callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $placeholder = '/\{([a-zA-Z_]+)\}/';
        preg_match_all($placeholder, $pattern, $matches);

        $literals = array_map(
            static fn (string $literal): string => preg_quote($literal, '#'),
            preg_split($placeholder, $pattern) ?: [],
        );
        $regex = '#^' . implode('([^/]+)', $literals) . '$#';

        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => $regex,
            'names' => $matches[1],
            'handler' => $handler,
        ];
    }

    /**
     * Returns null when no route matches the request path.
     *
     * @throws MethodNotAllowed when the path matches but the method does not
     */
    public function dispatch(Request $request): ?Response
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $captured) !== 1) {
                continue;
            }

            $pathMatched = true;
            if ($route['method'] !== $request->method) {
                continue;
            }

            array_shift($captured);
            /** @var array<string, string> $params */
            $params = array_combine($route['names'], array_map('urldecode', $captured));

            return ($route['handler'])($request, $params);
        }

        if ($pathMatched) {
            throw new MethodNotAllowed();
        }

        return null;
    }
}
