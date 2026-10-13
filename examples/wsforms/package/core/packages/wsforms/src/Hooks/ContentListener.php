<?php

declare(strict_types=1);

namespace WsForms\Hooks;

use VbLaravel\Attributes\Hook;

class ContentListener
{
    #[Hook('hookFrontendContentBeforeAdd', 10)]
    public function onBeforeContentAdd(array &$params): void
    {
        if (!vb()->setting('wsforms_enabled', true)) {
            return;
        }

        // Add form attachment context if applicable
        if (isset($params['form_submission_id'])) {
            $params['has_custom_form'] = true;
        }
    }
}
