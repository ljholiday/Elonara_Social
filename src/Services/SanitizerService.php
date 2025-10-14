<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Input Sanitizer Service
 *
 * Sanitization methods for different input types.
 * Sanitizers return clean values - they don't validate.
 */
final class SanitizerService
{
    /**
     * Sanitize HTML output (escape for display)
     */
    public function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize HTML attributes
     */
    public function escapeAttribute(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize URL for output
     */
    public function escapeUrl(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize text field input
     */
    public function textField(string $input): string
    {
        $filtered = strip_tags(trim($input));
        $filtered = preg_replace('/\s+/', ' ', $filtered);
        return $filtered;
    }

    /**
     * Sanitize email input
     */
    public function email(string $email): string
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sanitize URL input
     */
    public function url(string $url): string
    {
        return filter_var(trim($url), FILTER_SANITIZE_URL);
    }

    /**
     * Sanitize integer input
     */
    public function integer($input): int
    {
        return (int) $input;
    }

    /**
     * Sanitize float input
     */
    public function float($input): float
    {
        return (float) $input;
    }

    /**
     * Sanitize textarea input (plain text only)
     */
    public function textarea(string $input): string
    {
        $data = strip_tags(trim($input));
        $data = preg_replace('/\s+/', ' ', $data);
        return $data;
    }

    /**
     * Sanitize rich text content (allow safe HTML)
     */
    public function richText(string $input): string
    {
        $allowedTags = [
            'a' => ['href' => [], 'title' => [], 'target' => []],
            'b' => [],
            'strong' => [],
            'i' => [],
            'em' => [],
            'u' => [],
            'br' => [],
            'p' => [],
            'span' => ['class' => []],
            'div' => ['class' => []],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'h5' => [],
            'h6' => [],
            'blockquote' => [],
            'code' => [],
            'pre' => [],
            'img' => ['src' => [], 'alt' => [], 'width' => [], 'height' => [], 'class' => []],
        ];

        return $this->filterHtml($input, $allowedTags);
    }

    /**
     * Strip all tags from input
     */
    public function stripTags(string $input, bool $removeBreaks = false): string
    {
        $input = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $input);
        $input = strip_tags($input);

        if ($removeBreaks) {
            $input = preg_replace('/[\r\n\t ]+/', ' ', $input);
        }

        return trim($input);
    }

    /**
     * Sanitize filename
     */
    public function filename(string $filename): string
    {
        // Remove path separators and dangerous characters
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        return $filename;
    }

    /**
     * Sanitize slug (URL-friendly string)
     */
    public function slug(string $input): string
    {
        $slug = strtolower(trim($input));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    /**
     * Sanitize phone number
     */
    public function phoneNumber(string $phone): string
    {
        return preg_replace('/[^0-9+\-\(\)\s]/', '', $phone);
    }

    /**
     * Sanitize alphanumeric input
     */
    public function alphaNumeric(string $input): string
    {
        return preg_replace('/[^a-zA-Z0-9]/', '', $input);
    }

    /**
     * Sanitize array recursively
     *
     * @param array<mixed> $input
     * @return array<mixed>
     */
    public function sanitizeArray(array $input, string $method = 'textField'): array
    {
        $sanitized = [];
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value, $method);
            } else {
                $sanitized[$key] = $this->{$method}((string)$value);
            }
        }
        return $sanitized;
    }

    /**
     * Validate and sanitize JSON input
     *
     * @return array<mixed>|null
     */
    public function jsonInput(string $input): ?array
    {
        $decoded = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        return $this->sanitizeArray($decoded);
    }

    /**
     * Clean whitespace
     */
    public function cleanWhitespace(string $input): string
    {
        return preg_replace('/\s+/', ' ', trim($input));
    }

    /**
     * Remove invisible characters
     */
    public function removeInvisibleCharacters(string $input): string
    {
        $nonDisplayables = [];

        // Add ranges of non-displayable characters
        for ($i = 0; $i <= 31; $i++) {
            if ($i !== 9 && $i !== 10 && $i !== 13) { // Keep tab, newline, carriage return
                $nonDisplayables[] = chr($i);
            }
        }

        return str_replace($nonDisplayables, '', $input);
    }

    /**
     * Filter HTML with allowed tags and attributes
     *
     * @param array<string,array<string,array<mixed>>> $allowedTags
     */
    private function filterHtml(string $input, array $allowedTags): string
    {
        // Basic HTML filtering implementation
        // In production, consider using a library like HTMLPurifier

        $allowedTagsString = '<' . implode('><', array_keys($allowedTags)) . '>';
        $filtered = strip_tags($input, $allowedTagsString);

        // Remove dangerous attributes
        $filtered = preg_replace('/\s(on\w+|javascript:|data-)="[^"]*"/i', '', $filtered);

        return $filtered;
    }
}
