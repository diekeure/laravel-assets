<?php

Route::group(
    Config::get('assets.route'),
    function() {

        Route::get('{id}', 'AssetController@view');

    }
);