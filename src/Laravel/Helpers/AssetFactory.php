<?php

namespace CatLab\Assets\Laravel\Helpers;

use CatLab\Assets\Laravel\Models\Asset;
use Config;

/**
 * Class AssetFactory
 * @package CatLab\Assets\Laravel\Helpers
 */
class AssetFactory
{
    /**
     * @return string
     */
    public static function getAssetClassName()
    {
        return Config::get('assets.assetClassName', Asset::class);
    }

    /**
     * @param $attributes
     * @return Asset
     */
    public static function getNewInstance($attributes)
    {
        $className = self::getAssetClassName();
        return new $className($attributes);
    }

    /**
     * @param $id
     * @return mixed
     */
    public static function find($id)
    {
        $className = self::getAssetClassName();
        return call_user_func([ $className, 'find' ], $id);
    }

    /**
     * @return mixed
     */
    public static function getDefaultDisk()
    {
        $className = self::getAssetClassName();
        return call_user_func([ $className, 'getDefaultDisk' ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function query()
    {
        $className = self::getAssetClassName();
        return call_user_func([ $className, 'query' ]);
    }
}