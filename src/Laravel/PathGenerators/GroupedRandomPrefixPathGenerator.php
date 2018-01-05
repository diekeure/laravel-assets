<?php

namespace CatLab\Assets\Laravel\PathGenerators;

use CatLab\Assets\Laravel\Models\Asset;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class GroupedIdPathGenerator
 *
 * This path generator is similar to the GroupedIdPathGenerator:
 * - each asset gets their own folder
 * - all variations are stored in that specific folder
 *
 * However, on root level, a random 4-char prefix is added to
 * improve performance on s3.
 *
 * @package CatLab\Assets\Laravel
 */
class GroupedRandomPrefixPathGenerator extends PathGenerator
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

        // We take 4 characters from the hex encoded md5 hash.
        // We start at 7 for no reason at all.
        $hash = substr(md5($id), 7, 4);

        $path[] = $hash;

        // Second level path should simply be the asset id.
        $path[] = $id;

        return implode('/', $path);
    }
}
