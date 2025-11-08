<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Service for fetching Google Analytics data via GA4 Data API
 */
final class AnalyticsService
{
    private ?string $propertyId = null;
    private ?string $apiKey = null;

    public function __construct()
    {
        $config = app_config('analytics', []);
        $this->propertyId = $config['ga4_property_id'] ?? null;
        $this->apiKey = $config['ga4_api_key'] ?? null;
    }

    /**
     * Get basic analytics stats for the dashboard
     *
     * @return array{
     *   users_7d: int|null,
     *   sessions_7d: int|null,
     *   page_views_7d: int|null,
     *   users_30d: int|null,
     *   sessions_30d: int|null,
     *   page_views_30d: int|null,
     *   error: string|null
     * }
     */
    public function getDashboardStats(): array
    {
        $stats = [
            'users_7d' => null,
            'sessions_7d' => null,
            'page_views_7d' => null,
            'users_30d' => null,
            'sessions_30d' => null,
            'page_views_30d' => null,
            'error' => null,
        ];

        if ($this->propertyId === null || $this->propertyId === '') {
            $stats['error'] = 'GA4 Property ID not configured';
            return $stats;
        }

        if ($this->apiKey === null || $this->apiKey === '') {
            $stats['error'] = 'GA4 API Key not configured';
            return $stats;
        }

        try {
            // Fetch 7-day stats
            $data7d = $this->runReport('7daysAgo', 'today');
            if (isset($data7d['error'])) {
                $stats['error'] = $data7d['error'];
                return $stats;
            }

            if (!empty($data7d['rows'])) {
                $row = $data7d['rows'][0];
                $stats['users_7d'] = (int)($row['metricValues'][0]['value'] ?? 0);
                $stats['sessions_7d'] = (int)($row['metricValues'][1]['value'] ?? 0);
                $stats['page_views_7d'] = (int)($row['metricValues'][2]['value'] ?? 0);
            }

            // Fetch 30-day stats
            $data30d = $this->runReport('30daysAgo', 'today');
            if (isset($data30d['error'])) {
                $stats['error'] = $data30d['error'];
                return $stats;
            }

            if (!empty($data30d['rows'])) {
                $row = $data30d['rows'][0];
                $stats['users_30d'] = (int)($row['metricValues'][0]['value'] ?? 0);
                $stats['sessions_30d'] = (int)($row['metricValues'][1]['value'] ?? 0);
                $stats['page_views_30d'] = (int)($row['metricValues'][2]['value'] ?? 0);
            }
        } catch (\Exception $e) {
            $stats['error'] = 'Failed to fetch analytics: ' . $e->getMessage();
            error_log('[AnalyticsService] Error fetching GA4 data: ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Run a report via the GA4 Data API
     *
     * @return array<string, mixed>
     */
    private function runReport(string $startDate, string $endDate): array
    {
        $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$this->propertyId}:runReport?key={$this->apiKey}";

        $requestBody = [
            'dateRanges' => [
                [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ],
            ],
            'metrics' => [
                ['name' => 'activeUsers'],
                ['name' => 'sessions'],
                ['name' => 'screenPageViews'],
            ],
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            return ['error' => 'Failed to initialize cURL'];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['error' => 'cURL error: ' . $curlError];
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMsg = $data['error']['message'] ?? 'Unknown API error';
            error_log('[AnalyticsService] GA4 API error (HTTP ' . $httpCode . '): ' . $errorMsg);
            return ['error' => 'GA4 API error: ' . $errorMsg];
        }

        return $data;
    }

    /**
     * Check if analytics is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->propertyId !== null
            && $this->propertyId !== ''
            && $this->apiKey !== null
            && $this->apiKey !== '';
    }
}
