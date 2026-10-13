<?php

declare(strict_types=1);

require_once __DIR__ . '/product.php';

class wsforms_Hooks
{
    public static $order = 10;

    public static function hookFrontendContentBeforeAdd(array &$params): void
    {
        \VbLaravel\Runtime::boot();
        \wsforms\_Generated\HookDispatcher::hookFrontendContentBeforeAdd($params);
    }

}
