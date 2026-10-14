<?php

declare(strict_types=1);

return [
    'group_title' => 'Post Thanks Settings',
    'displayorder' => 1000,
    'settings' => [
        'wsthanks_enabled' => [
            'title' => 'Enable Post Thanks',
            'description' => 'Enable or disable the post thanks feature across the forum.',
            'type' => 'boolean',
            'default' => 1,
            'displayorder' => 10,
        ],
    ],
];
