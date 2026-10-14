<?php

declare(strict_types=1);

return [
    'group_title' => 'Thread Ratings Settings',
    'displayorder' => 1000,
    'settings' => [
        'wsratings_enabled' => [
            'title' => 'Enable Thread Ratings',
            'description' => 'Enable or disable the thread rating feature across the forum.',
            'type' => 'boolean',
            'default' => '1',
            'displayorder' => 10,
        ],
    ],
];
