<?php

return [

    'userClassName' => \App\Models\User::class,

    'cacheLifetime' => 60*60*24*365,
    'cachePrefix' => 'image:',

    'disk' => env('ASSETS_DISK', 'local'),

    'route' => [

        'prefix' => 'assets',
        'namespace' => 'CatLab\\Assets\\Laravel\\Controllers',
        'middleware' => [ 'web' ]

    ]

];