<?php

return [
    'graphql' => [
        'exclude_files' => [
            "/var/www/html/programmatic-types.graphql",
            "/var/www/html/schema-directives.graphql",
        ],
        'exclude_dirs' => [
            base_path('vendor'),
        ]
    ],
    'migration' => [
        'ignore_fields' => [
            'created_at',
            'updated_at'
        ]
    ]
];
