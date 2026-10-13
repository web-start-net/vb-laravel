<?php

declare(strict_types=1);

class wsforms_Hooks
{
    public static $order = 10;

    public static function hookFrontendContentBeforeAdd(array &$params): void
    {
        \wsforms\_Generated\HookDispatcher::hookFrontendContentBeforeAdd($params);
    }

}
