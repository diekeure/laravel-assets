<?php

return [

    'userClassName' => \App\Models\User::class,

    'route' => [

        'prefix' => 'assets',
        'namespace' => 'CatLab\\Assets\\Laravel\\Controllers',
        'middleware' => [ 'web' ]

    ]

];