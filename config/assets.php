<?php

return [

    'userClassName' => App\User::class,

    'route' => [

        'prefix' => 'assets',
        'namespace' => 'CatLab\\Assets\\Laravel\\Controllers',
        'middleware' => [ 'web' ]

    ]

];