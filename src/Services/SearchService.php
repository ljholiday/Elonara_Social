<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

final class SearchService
{
    /**
     * @var array<string,array{label:string,badge_class:string}>
     */
    private const TYPE_META = [
        'event' => ['label' => 'Event', 'badge_class' => 'app-badge app-badge-event'],
        'community' => ['label' => 'Community', 'badge_class' => 'app-badge app-badge-community'],
        'conversation' => ['label' => 'Conversation', 'badge_class' => 'app-badge app-badge-conversation'],
        'member' => ['label' => 'Member', 'badge_class' => 'app-badge app-badge-member'],
    ];

    public function __construct(private Database $database)
    {
    }

    /**
     * @return array<int,array<string,string>>
     */
    public function search(string $query, int $limit = 8, ?int $viewerId = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $pdo = $this->database->pdo();

        $sql = '
            SELECT title, content, url, entity_type, visibility_scope, owner_user_id
            FROM search
            WHERE (visibility_scope = :public_scope';

        $params = [
            ':public_scope' => 'public',
            ':like' => '%' . $query . '%',
            ':limit' => $limit,
        ];

        if ($viewerId !== null) {
            $sql .= ' OR owner_user_id = :viewer_id';
            $params[':viewer_id'] = $viewerId;
        }

        $sql .= ')
            AND (title LIKE :like OR content LIKE :like)
            ORDER BY last_activity_at DESC
            LIMIT :limit';

        $stmt = $pdo->prepare($sql);

        foreach ($params as $placeholder => $value) {
            $paramType = PDO::PARAM_STR;
            if ($placeholder === ':limit' || $placeholder === ':viewer_id') {
                $paramType = PDO::PARAM_INT;
            }
            $stmt->bindValue($placeholder, $value, $paramType);
        }

        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];

        foreach ($rows as $row) {
            $type = (string)($row['entity_type'] ?? 'content');
            $meta = self::TYPE_META[$type] ?? [
                'label' => ucfirst($type !== '' ? $type : 'Result'),
                'badge_class' => 'app-badge',
            ];

            $results[] = [
                'title' => (string)$row['title'],
                'url' => (string)$row['url'],
                'entity_type' => $type,
                'badge_label' => $meta['label'],
                'badge_class' => $meta['badge_class'],
                'snippet' => $this->buildSnippet((string)$row['content'], $query),
            ];
        }

        return $results;
    }

    private function buildSnippet(string $content, string $query): string
    {
        $text = strip_tags($content);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $lowerText = mb_strtolower($text);
        $lowerQuery = mb_strtolower($query);
        $position = mb_strpos($lowerText, $lowerQuery);

        $start = $position !== false ? max(0, $position - 40) : 0;
        $snippet = mb_substr($text, $start, 120);

        if ($start > 0) {
            $snippet = '…' . $snippet;
        }

        if ($start + 120 < mb_strlen($text)) {
            $snippet .= '…';
        }

        return $snippet;
    }
}
