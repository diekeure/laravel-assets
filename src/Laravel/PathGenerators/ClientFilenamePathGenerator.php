<?php

namespace CatLab\Assets\Laravel;

use CatLab\Assets\Laravel\Models\Asset;
use CatLab\Base\Helpers\StringHelper;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class ClientFilenamePathGenerator
 * @package CatLab\Assets\Laravel
 */
class ClientFilenamePathGenerator extends PathGenerator
{

    /**
     * @param Asset $asset
     * @param UploadedFile $file
     * @return string
     */
    public function generatePath(Asset $asset, UploadedFile $file)
    {
        $id = str_pad($asset->id, 6, '0', STR_PAD_LEFT);

        $extension = $file->getClientOriginalExtension();
        $filename = StringHelper::substr($file->getClientOriginalName(), 0, -(1 + StringHelper::length($extension)));

        return $id
            . '-' . StringHelper::escapeFileName($filename)
            . '.' . $extension;
    }

    /**
     * @return string
     */
    private function getUploadFolder()
    {
        return self::UPLOAD_FOLDER;
    }
}