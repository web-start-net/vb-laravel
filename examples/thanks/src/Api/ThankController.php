<?php

declare(strict_types=1);

namespace WsThanks\Api;

use VbLaravel\Attributes\Api;
use WsThanks\Models\Thank;

class ThankController
{
    #[Api('wsthanks:thank', 'get')]
    public function get(int $nodeid): array
    {
        $userId = vb()->userId();

        $count = Thank::query()
            ->where('nodeid', $nodeid)
            ->count();

        $userThanked = false;
        if ($userId > 0) {
            $userThanked = Thank::query()
                ->where('nodeid', $nodeid)
                ->where('userid', $userId)
                ->exists();
        }

        return [
            'nodeid' => $nodeid,
            'count' => (int) $count,
            'user_thanked' => (bool) $userThanked,
        ];
    }

    #[Api('wsthanks:thank', 'toggle', write: true)]
    public function toggle(int $nodeid): array
    {
        $userId = vb()->userId();
        if ($userId === 0) {
            throw new \vB_Exception_Api('no_permission');
        }

        $existing = Thank::query()
            ->where('nodeid', $nodeid)
            ->where('userid', $userId)
            ->first();

        if ($existing) {
            $existing->delete();
            $thanked = false;
        } else {
            Thank::create([
                'nodeid' => $nodeid,
                'userid' => $userId,
                'created_at' => time(),
            ]);
            $thanked = true;
        }

        $count = Thank::query()
            ->where('nodeid', $nodeid)
            ->count();

        return [
            'success' => true,
            'nodeid' => $nodeid,
            'thanked' => (bool) $thanked,
            'count' => (int) $count,
        ];
    }
}
