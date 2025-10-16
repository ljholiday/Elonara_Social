<?php
declare(strict_types=1);

namespace App\Support;

final class VisibilityBadge
{
    /**
     * Build badge metadata for entity privacy/visibility.
     *
     * @param string|null $privacy Raw privacy flag ('public', 'private', etc.)
     * @return array{status:string,label:string,class:string}
     */
    public static function for(?string $privacy): array
    {
        $status = strtolower(trim((string)$privacy));
        if ($status === '') {
            $status = 'public';
        }

        if ($status !== 'private') {
            return [
                'status' => 'public',
                'label' => 'Public',
                'class' => 'app-badge app-badge-public',
            ];
        }

        return [
            'status' => 'private',
            'label' => 'Private',
            'class' => 'app-badge app-badge-private',
        ];
    }
}
