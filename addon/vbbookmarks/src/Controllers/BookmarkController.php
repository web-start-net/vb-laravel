<?php

declare(strict_types=1);

namespace VB\Bookmarks\Controllers;

use VB\Bookmarks\Models\Bookmark;

final class BookmarkController
{
    public function index(): array
    {
        $user = vb()->user();
        if (!$this->authorized($user)) {
            return ['authorized' => false, 'bookmarks' => []];
        }

        $bookmarks = [];
        foreach (Bookmark::query()->where('userid', (int) $user['userid'])->latest('id')->get() as $bookmark) {
            $nodeid = (int) $bookmark->nodeid;
            $node = vb()->node($nodeid);
            if (!is_array($node) || empty($node['nodeid'])) {
                continue;
            }

            $bookmarks[] = [
                'nodeid' => $nodeid,
                'title' => (string) ($node['title'] ?? ('Node ' . $nodeid)),
                'url' => vb()->url((string) $node['routeid'] . '|fullurl', ['nodeid' => $nodeid]),
                'created_at' => (string) $bookmark->created_at,
            ];
        }

        return [
            'authorized' => true,
            'securitytoken' => (string) ($user['securitytoken'] ?? ''),
            'bookmarks' => $bookmarks,
            'urls' => [
                'index' => vb()->url('vbbookmarks|fullurl'),
                'add' => vb()->url('vbbookmarks|fullurl'),
                'remove' => vb()->url('vbbookmarks|fullurl'),
            ],
        ];
    }

    public function store(int $nodeid, string $securitytoken): array
    {
        $user = vb()->user();
        if (!$this->authorized($user) || !$this->validPost($securitytoken, $user)) {
            return $this->index();
        }

        $node = vb()->node($nodeid);
        if (is_array($node) && !empty($node['nodeid'])) {
            Bookmark::query()->insertOrIgnore([
                'userid' => (int) $user['userid'],
                'nodeid' => $nodeid,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->index();
    }

    public function destroy(int $nodeid, string $securitytoken): array
    {
        $user = vb()->user();
        if (!$this->authorized($user) || !$this->validPost($securitytoken, $user)) {
            return $this->index();
        }

        Bookmark::query()
            ->where('userid', (int) $user['userid'])
            ->where('nodeid', $nodeid)
            ->delete();

        return $this->index();
    }

    private function authorized(array $user): bool
    {
        return (int) ($user['userid'] ?? 0) > 0 && vb()->can('can_use_bookmarks');
    }

    private function validPost(string $securitytoken, array $user): bool
    {
        $nativeUser = class_exists('vB_User') ? \vB_User::fetchUserinfo((int) ($user['userid'] ?? 0)) : [];
        $securitytokenRaw = (string) ($nativeUser['securitytoken_raw'] ?? ($user['securitytoken_raw'] ?? ''));

        return strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && class_exists('vB_User')
            && \vB_User::verifySecurityToken($securitytoken, $securitytokenRaw);
    }
}
