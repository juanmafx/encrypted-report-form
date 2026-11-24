<?php

declare(strict_types=1);

namespace Fang\Http;

use InvalidArgumentException;
use RuntimeException;

final readonly class Renderer
{
    private const array TEMPLATES = ['report_form', 'thanks', 'error', 'admin_login', 'admin_two_factor', 'admin_dashboard', 'admin_report'];

    public function __construct(private string $templateDirectory)
    {
    }

    public function page(string $template, string $title, array $view = []): string
    {
        if (!in_array($template, self::TEMPLATES, true)) {
            throw new InvalidArgumentException('Unknown template.');
        }
        $content = $this->capture($this->templateDirectory . '/' . $template . '.php', $view);

        return $this->capture($this->templateDirectory . '/layout.php', ['title' => $title, 'content' => $content]);
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private function capture(string $file, array $view): string
    {
        $escape = self::escape(...);
        ob_start();
        try {
            (static function (string $__file, array $view, \Closure $e): void {
                require $__file;
            })($file, $view, $escape);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if ($output === false) {
            throw new RuntimeException('Template output could not be captured.');
        }

        return $output;
    }
}
