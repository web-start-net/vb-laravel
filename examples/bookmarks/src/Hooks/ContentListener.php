<?php

declare(strict_types=1);

namespace WsBookmarks\Hooks;

use VbLaravel\Attributes\Hook;

class ContentListener
{
    #[Hook('hookFrontendPreheader', 10)]
    public function onPreheader(array &$params): void
    {
        $enabled = vb()->setting('wsbookmarks_enabled');
        // This handler only queries vB settings and does not boot Illuminate
    }

    #[Hook('hookFrontendContentAfterAdd', 10)]
    public function onContentAdded(array &$params): void
    {
        if (!vb()->setting('wsbookmarks_enabled', true)) {
            return;
        }

        $nodeId = (int) ($params['nodeid'] ?? ($params['data']['nodeid'] ?? 0));
        $userId = vb()->userId();

        if ($nodeId > 0 && $userId > 0) {
            $params['ws_bookmarked'] = true;
            if (isset($params['data']) && is_array($params['data'])) {
                $params['data']['ws_bookmarked'] = true;
            }
        }
    }
}
