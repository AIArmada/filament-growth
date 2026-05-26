<?php

declare(strict_types=1);

return [
    /* Navigation */
    'navigation_group' => 'Growth',

    /* Tables */
    'tables' => [
        'stats_experiment_limit' => 10,
    ],

    /* Features */
    'features' => [
        'dashboard' => true,
        'results' => true,
        'widgets' => true,
        'experiments' => true,
        'variants' => true,
        'settings_page' => true,
    ],

    /* Resources */
    'resources' => [
        'navigation_sort' => [
            'dashboard' => 10,
            'results' => 11,
            'experiments' => 20,
            'variants' => 21,
            'settings' => 99,
        ],
    ],
];
