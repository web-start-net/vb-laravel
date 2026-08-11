<?php

declare(strict_types=1);

class vbbookmarks_Api_Bookmarks extends vB_Api
{
    public function fetch(): array
    {
        \VbLaravel\AddonLoader::load(DIR . '/packages/vbbookmarks');

        require_once DIR . '/packages/vbbookmarks/src/Models/Bookmark.php';
        require_once DIR . '/packages/vbbookmarks/src/Controllers/BookmarkController.php';

        $controller = new \VB\Bookmarks\Controllers\BookmarkController();
        $action = (string) ($_POST['bookmark_action'] ?? 'index');
        $nodeid = filter_var($_POST['nodeid'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
        $token = (string) ($_POST['securitytoken'] ?? '');

        return match ($action) {
            'store' => $controller->store($nodeid, $token),
            'destroy' => $controller->destroy($nodeid, $token),
            default => $controller->index(),
        };
    }
}
