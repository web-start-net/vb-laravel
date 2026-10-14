<?php

declare(strict_types=1);

namespace WsRatings\Hooks;

use VbLaravel\Attributes\Hook;

class ContentListener
{
    #[Hook('hookFrontendPreheader', 10)]
    public function onPreheader(array &$params): void
    {
        // Zero-overhead check: only query vB settings without booting Illuminate
        $enabled = vb()->setting('wsratings_enabled', true);
    }
}
