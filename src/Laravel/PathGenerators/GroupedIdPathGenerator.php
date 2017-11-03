<?php

namespace CatLab\Assets\Laravel\PathGenerators;

use CatLab\Assets\Laravel\Models\Asset;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class GroupedIdPathGenerator
 *
 * Put assets in folders based on the id of the asset.
 * Obfuscate the actual filename with md5.
 * Group by 100s (so each folder will have a maximum of 100 folders)
 * Each asset gets their own folder.
 *
 * @package CatLab\Assets\Laravel
 */
class GroupedIdPathGenerator extends PathGenerator
{
    /**
     * @param Asset $asset
     * @param UploadedFile $file
     * @return string
     */
    public function generatePath(Asset $asset, UploadedFile $file)
    {
        $assetPath = $this->getFolderFromId($asset->getRootAsset()->id);
        $extension = $file->getClientOriginalExtension();

        if ($asset->id === $asset->getRootAsset()->id) {
            $filename = str_random(8);
        } else {
            $filename = 'v' . $asset->variations->count() . '-' . str_random(8);
        }


        return $this->getUploadFolder() . '/' . $assetPath
            . '/' . $filename
            . '.' . $extension;
    }

    /**
     * This is not the code you're looking for.
     * @param int $id
     * @return string
     */
    public function getFolderFromId($id)
    {
        $path = [];

        $rootFolder = 6;
        while ($rootFolder > 0) {
            $r = floor($id / pow(10, $rootFolder));
            $path[] = $r;
            $id -=  $r * pow(10, $rootFolder);
            $rootFolder -= 3;
        }

        $path[] = $id;

        foreach ($path as $k => $v) {
            $path[$k] = str_pad($v, 3, '0', STR_PAD_LEFT);
        }

        return implode('/', $path);
    }
}