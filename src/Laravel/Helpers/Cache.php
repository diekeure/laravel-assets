<?php

namespace CatLab\Assets\Laravel\Helpers;

/**
 * Class Cache
 * @package CatLab\Assets\Laravel\Helpers
 */
class Cache
{
    /**
     * @return Cache
     */
    public static function instance()
    {
        static $in;
        if (!isset($in)) {
            $in = new self();
        }
        return $in;
    }

    public function has($key)
    {
        return file_exists($this->getPath($key));
    }

    public function get($key)
    {
        return file_get_contents($this->getPath($key));
    }

    public function put($key, $content, $lifetime = null)
    {
        file_put_contents($this->getPath($key), $content);
    }

    /**
     * @param $key
     * @return string
     */
    protected function getPath($key)
    {
        return storage_path('image_cache/' . $key);
    }
}