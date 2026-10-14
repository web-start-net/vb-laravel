<?php

declare(strict_types=1);

namespace WsBookmarks\Api;

use VbLaravel\Attributes\Api;
use VbLaravel\Attributes\Can;
use WsBookmarks\Models\Bookmark;

class BookmarkController
{
    #[Api('wsbookmarks:bookmark', 'get')]
    #[Can('canview', 'genericpermissions')]
    public function get(int $nodeid): array
    {
        $userId = vb()->userId();
        if ($userId === 0) {
            return [
                'bookmarked' => false,
                'count' => Bookmark::query()->where('nodeid', $nodeid)->count(),
            ];
        }

        $isBookmarked = Bookmark::query()
            ->where('nodeid', $nodeid)
            ->where('userid', $userId)
            ->exists();

        $count = Bookmark::query()
            ->where('nodeid', $nodeid)
            ->count();

        return [
            'bookmarked' => $isBookmarked,
            'count' => $count,
        ];
    }

    #[Api('wsbookmarks:bookmark', 'toggle', write: true)]
    #[Can('canview', 'genericpermissions')]
    public function toggle(int $nodeid, string $note = ''): array
    {
        $userId = vb()->userId();
        if ($userId === 0) {
            throw new \vB_Exception_Api('no_permission');
        }

        $existing = Bookmark::query()
            ->where('nodeid', $nodeid)
            ->where('userid', $userId)
            ->first();

        if ($existing) {
            $existing->delete();
            $bookmarked = false;
        } else {
            Bookmark::create([
                'nodeid' => $nodeid,
                'userid' => $userId,
                'note' => $note,
                'created_at' => time(),
            ]);
            $bookmarked = true;
        }

        $count = Bookmark::query()
            ->where('nodeid', $nodeid)
            ->count();

        return [
            'success' => true,
            'bookmarked' => $bookmarked,
            'count' => $count,
        ];
    }
}
