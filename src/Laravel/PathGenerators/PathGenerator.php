<?php

namespace CatLab\Assets\Laravel\PathGenerators;

use Config;
use CatLab\Assets\Laravel\Models\Asset;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class PathGenerator
 * @package CatLab\Assets\Laravel
 */
abstract class PathGenerator
{
    const UPLOAD_FOLDER = 'uploads';

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
     * @return string
     */
    public function getUploadFolder()
    {
        return self::UPLOAD_FOLDER;
    }

    /**
     * @param Asset $asset
     * @param UploadedFile $file
     * @return string
     */
    abstract function generatePath(Asset $asset, UploadedFile $file);
}