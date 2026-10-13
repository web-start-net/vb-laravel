<?php

declare(strict_types=1);

class wsbookmarks_Hooks
{
    public static $order = 10;

    public static function hookFrontendContentAfterAdd(array &$params): void
    {
        \wsbookmarks\_Generated\HookDispatcher::hookFrontendContentAfterAdd($params);
    }

}
