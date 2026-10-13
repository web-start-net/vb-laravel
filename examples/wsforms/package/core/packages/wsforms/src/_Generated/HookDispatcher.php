<?php

declare(strict_types=1);

namespace wsforms\_Generated;

final class HookDispatcher
{
    public static function hookFrontendContentBeforeAdd(array &$params): void
    {
        (new \WsForms\Hooks\ContentListener())->onBeforeContentAdd($params);
    }

}
