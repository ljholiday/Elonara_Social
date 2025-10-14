<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function date_fmt(?string $iso, string $fmt = 'M j, Y'): string
{
    if (!$iso) {
        return '';
    }

    $timestamp = strtotime($iso);

    return $timestamp ? date($fmt, $timestamp) : '';
}

function url_for(string $base, array $params = []): string
{
    return $params ? $base . '?' . http_build_query($params) : $base;
}

function vt_time_ago(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return '';
    }

    $timestamp = strtotime($iso);
    if ($timestamp === false) {
        return '';
    }

    $diff = time() - $timestamp;
    if ($diff < 0) {
        $diff = 0;
    }

    $units = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second',
    ];

    foreach ($units as $seconds => $label) {
        if ($diff >= $seconds) {
            $value = (int)floor($diff / $seconds);
            return $value . ' ' . $label . ($value === 1 ? '' : 's') . ' ago';
        }
    }

    return 'just now';
}

function vt_truncate_words(?string $text, int $limit = 25, string $ellipsis = '…'): string
{
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }

    $words = preg_split('/\s+/u', strip_tags($text)) ?: [];
    if (count($words) <= $limit) {
        return implode(' ', $words);
    }

    return implode(' ', array_slice($words, 0, $limit)) . $ellipsis;
}

/**
 * Render a template within a layout
 *
 * @param string $template_path Path to content template (relative to templates/)
 * @param array<string,mixed> $data Data to extract for template
 * @param string $layout Layout to use ('page', 'form', 'two-column', or 'guest')
 * @return void
 */
function vt_render(string $template_path, array $data = [], string $layout = 'page'): void
{
    // Extract data for template
    extract($data, EXTR_SKIP);

    // Capture template output
    ob_start();
    require __DIR__ . '/' . $template_path;
    $content = ob_get_clean();

    // Set layout variables
    $page_title = $data['page_title'] ?? 'Elonara Social';
    $page_description = $data['page_description'] ?? '';
    $current_path = $_SERVER['REQUEST_URI'] ?? '/';
    $breadcrumbs = $data['breadcrumbs'] ?? [];
    $nav_items = $data['nav_items'] ?? [];

    // For two-column layout, use content as main_content if not specified
    if ($layout === 'two-column') {
        $main_content = $data['main_content'] ?? $content;
        $sidebar_content = $data['sidebar_content'] ?? '';
    }

    // Render layout with content
    require __DIR__ . '/layouts/' . $layout . '.php';
}
