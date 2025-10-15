<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\AuthService;
use App\Services\SearchService;

final class SearchController
{
    public function __construct(
        private SearchService $search,
        private AuthService $auth
    ) {
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function search(Request $request): array
    {
        $query = (string)$request->query('q', '');
        $viewer = $this->auth->getCurrentUser();

        $results = $this->search->search($query, 8, $viewer?->id ?? null);

        return [
            'status' => 200,
            'body' => [
                'results' => $results,
            ],
        ];
    }
}
