<?php

namespace CatLab\Assets\Laravel\Helpers;

/**
 * Class Cache
 * @package CatLab\Assets\Laravel\Helpers
 */
class Cache
{
    private $path;

    /**
     * @return Cache
     */
    public static function instance()
    {
        static $in;
        if (!isset($in)) {
            $in = new self(storage_path());
        }
        return $in;
    }

    /**
     * Cache constructor.
     * @param $path
     */
    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * @param $key
     * @return bool
     */
    public function has($key)
    {
        return file_exists($this->getPath($key));
    }

    /**
     * @param $key
     * @return bool|string
     */
    public function get($key)
    {
        return file_get_contents($this->getPath($key));
    }

    /**
     * @param $key
     * @param $content
     * @param null $lifetime
     */
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
        return $this->path . '/image_cache/' . str_replace('/', '_', $key);
    }
}