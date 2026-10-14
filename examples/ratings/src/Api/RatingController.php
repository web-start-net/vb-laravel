<?php

declare(strict_types=1);

namespace WsRatings\Api;

use VbLaravel\Attributes\Api;
use VbLaravel\Attributes\Can;
use WsRatings\Models\Rating;

class RatingController
{
    #[Api('wsratings:rating', 'get')]
    #[Can('canview', 'genericpermissions')]
    public function get(int $nodeid): array
    {
        $userId = vb()->userId();

        $stats = Rating::query()
            ->where('nodeid', $nodeid)
            ->selectRaw('COUNT(*) as total_ratings, AVG(rating) as average_rating')
            ->first();

        $userRating = null;
        if ($userId > 0) {
            $userRating = Rating::query()
                ->where('nodeid', $nodeid)
                ->where('userid', $userId)
                ->value('rating');
        }

        return [
            'nodeid' => $nodeid,
            'total_ratings' => (int) ($stats->total_ratings ?? 0),
            'average_rating' => round((float) ($stats->average_rating ?? 0), 2),
            'user_rating' => $userRating !== null ? (int) $userRating : null,
        ];
    }

    #[Api('wsratings:rating', 'rate', write: true)]
    #[Can('canview', 'genericpermissions')]
    public function rate(int $nodeid, int $rating): array
    {
        if ($rating < 1 || $rating > 5) {
            throw new \vB_Exception_Api('invalid_rating');
        }

        $userId = vb()->userId();
        if ($userId === 0) {
            throw new \vB_Exception_Api('no_permission');
        }

        $now = time();
        $record = Rating::updateOrCreate(
            ['nodeid' => $nodeid, 'userid' => $userId],
            [
                'rating' => $rating,
                'dateline' => $now,
            ]
        );

        $stats = Rating::query()
            ->where('nodeid', $nodeid)
            ->selectRaw('COUNT(*) as total_ratings, AVG(rating) as average_rating')
            ->first();

        return [
            'success' => true,
            'nodeid' => $nodeid,
            'rating' => (int) $record->rating,
            'user_rating' => (int) $record->rating,
            'total_ratings' => (int) ($stats->total_ratings ?? 0),
            'average_rating' => round((float) ($stats->average_rating ?? 0), 2),
        ];
    }
}
