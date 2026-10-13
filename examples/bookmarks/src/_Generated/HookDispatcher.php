<?php

declare(strict_types=1);

namespace wsbookmarks\_Generated;

final class HookDispatcher
{
    public static function hookFrontendContentAfterAdd(array &$params): void
    {
        (new \WsBookmarks\Hooks\ContentListener())->onContentAdded($params);
    }

}
