<?php
declare(strict_types=1);

namespace App\Support;

final class ContextLabel
{
    /**
     * @param array<int,array{label:string,url:?string}> $contextParts
     */
    public static function render(array $contextParts): string
    {
        $segments = [];
        foreach ($contextParts as $part) {
            $label = trim((string)($part['label'] ?? ''));
            $url = $part['url'] ?? null;

            if ($label === '') {
                continue;
            }

            $segments[] = $url ? sprintf('<a href="%s">%s</a>', htmlspecialchars((string)$url, ENT_QUOTES, 'UTF-8'), htmlspecialchars($label, ENT_QUOTES, 'UTF-8'))
                : htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        }

        return implode(' : ', $segments);
    }

    /**
     * @param array<int,array{label:string,url:?string}> $contextParts
     */
    public static function renderPlain(array $contextParts): string
    {
        $segments = [];
        foreach ($contextParts as $part) {
            $label = trim((string)($part['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $segments[] = $label;
        }

        return implode(' : ', $segments);
    }
}
