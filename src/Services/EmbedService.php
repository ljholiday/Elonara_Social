<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Embed\HttpClient;
use App\Services\Embed\OEmbedProvider;
use App\Services\Embed\OpenGraphProvider;

/**
 * Embed Service
 *
 * Main orchestration service for URL embeds with caching.
 */
final class EmbedService
{
    private const CACHE_DURATION = 86400; // 24 hours

    private HttpClient $httpClient;
    private OEmbedProvider $oembedProvider;
    private OpenGraphProvider $ogProvider;

    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    public function __construct()
    {
        $this->httpClient = new HttpClient();
        $this->oembedProvider = new OEmbedProvider($this->httpClient);
        $this->ogProvider = new OpenGraphProvider($this->httpClient);
    }

    /**
     * Build embed data from URL
     *
     * @return array<string, mixed>|null
     */
    public function buildFromUrl(string $url): ?array
    {
        if (empty($url)) {
            return null;
        }

        // Check memory cache first
        $cacheKey = $this->getCacheKey($url);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey] === false ? null : $this->cache[$cacheKey];
        }

        // Try oEmbed for known video providers
        $embed = null;
        if ($this->oembedProvider->isSupported($url)) {
            $oembedData = $this->oembedProvider->fetch($url);
            // Only use oEmbed if it's a video type
            if ($oembedData && ($oembedData['oembed_type'] ?? '') === 'video') {
                $embed = $oembedData;
            }
        }

        // If no video embed, use Open Graph for link cards
        if (!$embed) {
            $embed = $this->ogProvider->fetch($url);
        }

        // Cache result (including failures)
        $this->cache[$cacheKey] = $embed ?: false;

        return $embed;
    }

    /**
     * Extract first URL from text content
     */
    public function extractFirstUrl(string $text): ?string
    {
        $pattern = '#\bhttps?://[^\s<>"]+#i';
        if (preg_match($pattern, $text, $matches)) {
            return $matches[0];
        }
        return null;
    }

    /**
     * Process content to add embeds
     */
    public function processContent(string $content): string
    {
        if (empty($content)) {
            return '';
        }

        // Extract first URL
        $url = $this->extractFirstUrl($content);
        if (!$url) {
            return $content;
        }

        // Get embed data
        $embed = $this->buildFromUrl($url);
        if (!$embed || !$this->shouldRender($embed)) {
            return $content;
        }

        // Append rendered embed
        return $content . $this->render($embed);
    }

    /**
     * Check if embed should be rendered
     *
     * @param array<string, mixed> $embed
     */
    public function shouldRender(array $embed): bool
    {
        if (empty($embed['type'])) {
            return false;
        }

        // Always render video embeds
        if ($embed['type'] === 'video' || $embed['type'] === 'rich') {
            return !empty($embed['html']);
        }

        // Render link cards if they have at least title
        if ($embed['type'] === 'link') {
            return !empty($embed['title']);
        }

        return false;
    }

    /**
     * Render embed HTML
     *
     * @param array<string, mixed> $embed
     */
    public function render(array $embed): string
    {
        $type = $embed['type'] ?? 'link';

        return match ($type) {
            'video', 'rich' => $this->renderVideo($embed),
            'link' => $this->renderLinkCard($embed),
            default => '',
        };
    }

    /**
     * Render video/rich embed
     *
     * @param array<string, mixed> $embed
     */
    private function renderVideo(array $embed): string
    {
        if (empty($embed['html'])) {
            return '';
        }

        // Sanitize iframe HTML
        $html = $this->sanitizeIframe($embed['html']);

        $provider = htmlspecialchars($embed['provider_name'] ?? 'Video', ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<div class="vt-embed vt-embed-rich"><div class="vt-embed-iframe">%s</div><div class="vt-embed-meta">%s</div></div>',
            $html,
            $provider
        );
    }

    /**
     * Render link preview card
     *
     * @param array<string, mixed> $embed
     */
    private function renderLinkCard(array $embed): string
    {
        $url = htmlspecialchars($embed['url'] ?? '', ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($embed['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($embed['description'] ?? '', ENT_QUOTES, 'UTF-8');
        $provider = htmlspecialchars($embed['provider_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $image = $embed['image'] ?? null;

        $imageHtml = '';
        if ($image) {
            $imageSafe = htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
            $imageHtml = sprintf(
                '<div class="vt-embed-image-wrapper"><img src="%s" alt="" class="vt-embed-image" loading="lazy"></div>',
                $imageSafe
            );
        }

        $descriptionHtml = $description ? sprintf('<p class="vt-embed-description">%s</p>', $description) : '';
        $providerHtml = $provider ? sprintf('<div class="vt-embed-provider">%s</div>', $provider) : '';

        return sprintf(
            '<div class="vt-embed vt-embed-card"><a href="%s" class="vt-embed-link" target="_blank" rel="noopener noreferrer">%s<div class="vt-embed-content"><h4 class="vt-embed-title">%s</h4>%s%s</div></a></div>',
            $url,
            $imageHtml,
            $title,
            $descriptionHtml,
            $providerHtml
        );
    }

    /**
     * Sanitize iframe HTML
     */
    private function sanitizeIframe(string $html): string
    {
        // Extract iframe attributes
        if (!preg_match('/<iframe[^>]*src="([^"]*)"[^>]*>/i', $html, $matches)) {
            return '';
        }

        $src = $matches[1];

        // Rebuild iframe with safe attributes only
        return sprintf(
            '<iframe src="%s" frameborder="0" allowfullscreen sandbox="allow-scripts allow-same-origin allow-presentation" loading="lazy"></iframe>',
            htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
        );
    }

    private function getCacheKey(string $url): string
    {
        return 'embed_' . md5($url);
    }
}
