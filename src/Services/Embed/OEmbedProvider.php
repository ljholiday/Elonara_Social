<?php
declare(strict_types=1);

namespace App\Services\Embed;

/**
 * oEmbed Provider
 *
 * Handles oEmbed discovery and fetching for known providers.
 */
final class OEmbedProvider
{
    private const KNOWN_PROVIDERS = [
        'youtube.com' => 'https://www.youtube.com/oembed',
        'youtu.be' => 'https://www.youtube.com/oembed',
        'vimeo.com' => 'https://vimeo.com/api/oembed.json',
        'twitter.com' => 'https://publish.twitter.com/oembed',
        'x.com' => 'https://publish.twitter.com/oembed',
        'soundcloud.com' => 'https://soundcloud.com/oembed',
        'spotify.com' => 'https://open.spotify.com/oembed',
        'codepen.io' => 'https://codepen.io/api/oembed',
        'figma.com' => 'https://www.figma.com/api/oembed',
        'dailymotion.com' => 'https://www.dailymotion.com/services/oembed',
    ];

    public function __construct(private HttpClient $httpClient)
    {
    }

    /**
     * Check if URL is from a supported provider
     */
    public function isSupported(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        $host = strtolower(str_replace('www.', '', $host));

        foreach (array_keys(self::KNOWN_PROVIDERS) as $provider) {
            if (str_contains($host, $provider)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch oEmbed data for URL
     *
     * @return array<string, mixed>|null
     */
    public function fetch(string $url): ?array
    {
        $oembedUrl = $this->getOEmbedUrl($url);
        if (!$oembedUrl) {
            return null;
        }

        $response = $this->httpClient->getJson($oembedUrl);

        if (!$response['success'] || !isset($response['data'])) {
            return null;
        }

        return $this->normalizeResponse($response['data'], $url);
    }

    /**
     * Get oEmbed endpoint URL for a content URL
     */
    private function getOEmbedUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        $host = strtolower(str_replace('www.', '', $host));

        foreach (self::KNOWN_PROVIDERS as $provider => $endpoint) {
            if (str_contains($host, $provider)) {
                return $endpoint . '?' . http_build_query(['url' => $url, 'format' => 'json']);
            }
        }

        return null;
    }

    /**
     * Normalize oEmbed response to common format
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeResponse(array $data, string $url): array
    {
        $type = $data['type'] ?? 'link';

        return [
            'type' => $type,
            'oembed_type' => $type,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'author_name' => $data['author_name'] ?? null,
            'author_url' => $data['author_url'] ?? null,
            'provider_name' => $data['provider_name'] ?? null,
            'provider_url' => $data['provider_url'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'thumbnail_width' => $data['thumbnail_width'] ?? null,
            'thumbnail_height' => $data['thumbnail_height'] ?? null,
            'html' => $data['html'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'url' => $data['url'] ?? $url,
            'cache_age' => $data['cache_age'] ?? 86400,
        ];
    }
}
