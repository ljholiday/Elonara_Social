<?php
declare(strict_types=1);

namespace App\Services\Embed;

use DOMDocument;
use DOMXPath;

/**
 * Open Graph Provider
 *
 * Parses Open Graph meta tags for link preview cards.
 */
final class OpenGraphProvider
{
    public function __construct(private HttpClient $httpClient)
    {
    }

    /**
     * Fetch Open Graph data from URL
     *
     * @return array<string, mixed>|null
     */
    public function fetch(string $url): ?array
    {
        $response = $this->httpClient->get($url, ['timeout' => 5]);

        if (!$response['success'] || empty($response['body'])) {
            return null;
        }

        return $this->parseHtml($response['body'], $url);
    }

    /**
     * Parse HTML for Open Graph tags
     *
     * @return array<string, mixed>|null
     */
    private function parseHtml(string $html, string $url): ?array
    {
        // Suppress errors for malformed HTML
        $previousValue = libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);

        libxml_use_internal_errors($previousValue);

        $xpath = new DOMXPath($doc);

        // Extract all meta tags
        $metaTags = $xpath->query('//meta[@property or @name]');
        if ($metaTags === false || $metaTags->length === 0) {
            return null;
        }

        $data = [];

        foreach ($metaTags as $tag) {
            $property = $tag->getAttribute('property') ?: $tag->getAttribute('name');
            $content = $tag->getAttribute('content');

            if ($property && $content) {
                $data[$property] = $content;
            }
        }

        return $this->normalizeData($data, $url);
    }

    /**
     * Normalize extracted data to common format
     *
     * @param array<string, string> $data
     * @return array<string, mixed>|null
     */
    private function normalizeData(array $data, string $url): ?array
    {
        // Require at least title or og:title
        if (empty($data['og:title']) && empty($data['title'])) {
            return null;
        }

        return [
            'type' => 'link',
            'title' => $data['og:title'] ?? $data['twitter:title'] ?? $data['title'] ?? null,
            'description' => $data['og:description'] ?? $data['twitter:description'] ?? $data['description'] ?? null,
            'image' => $data['og:image'] ?? $data['twitter:image'] ?? null,
            'url' => $data['og:url'] ?? $url,
            'site_name' => $data['og:site_name'] ?? null,
            'provider_name' => $data['og:site_name'] ?? $this->extractDomain($url),
            'og_type' => $data['og:type'] ?? null,
            'cache_age' => 604800, // 7 days
        ];
    }

    private function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return 'Unknown';
        }

        return str_replace('www.', '', $host);
    }
}
