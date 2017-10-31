<?php

namespace CatLab\Assets\Laravel;

use CatLab\Assets\Laravel\Models\Asset;
use Config;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class PathGenerator
 * @package CatLab\Assets\Laravel
 */
abstract class PathGenerator
{
    /**
     * @return PathGenerator
     */
    public static function getPathGenerator()
    {
        static $in;

        if (!isset($in)) {
            $pathGenerator = Config::get('assets.pathGenerator', ClientFilenamePathGenerator::class);
            $in = new $pathGenerator();

            if (! ($in instanceof PathGenerator)) {
                throw new \InvalidArgumentException("Path generator must extend " . PathGenerator::class);
            }
        }

        return $in;
    }

    /**
     * @param Asset $asset
     * @return string
     */
    abstract function generatePath(Asset $asset, UploadedFile $file);
}