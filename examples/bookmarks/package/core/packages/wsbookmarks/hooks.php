<?php

declare(strict_types=1);

require_once __DIR__ . '/product.php';

class wsbookmarks_Hooks
{
    public static $order = 10;

    public static function hookFrontendContentAfterAdd(array &$params): void
    {
        \VbLaravel\Runtime::boot();
        \wsbookmarks\_Generated\HookDispatcher::hookFrontendContentAfterAdd($params);
    }

}
