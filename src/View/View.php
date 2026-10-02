<?php

declare(strict_types=1);

namespace Chess\View;

use RuntimeException;
use Throwable;

/** Renders a plain PHP template inside the shared layout. */
final class View
{
    public function __construct(private readonly string $directory)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $content = $this->capture($template, $data);

        return $this->capture('layout', [
            'title' => $data['title'] ?? 'Chess',
            'content' => $content,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function capture(string $template, array $data): string
    {
        $path = $this->directory . '/' . $template . '.php';
        if (!is_file($path)) {
            throw new RuntimeException("Unknown template: {$template}");
        }

        ob_start();
        try {
            (static function (string $__path, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__path;
            })($path, $data);
        } catch (Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return (string) ob_get_clean();
    }
}
