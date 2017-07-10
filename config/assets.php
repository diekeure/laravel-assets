<?php

return [

    'userClassName' => \App\Models\User::class,

    'disk' => env('ASSETS_DISK', 'local'),

    'route' => [

        'prefix' => 'assets',
        'namespace' => 'CatLab\\Assets\\Laravel\\Controllers',
        'middleware' => [ 'web' ]

    ]

];