<?php

namespace CatLab\Assets\Laravel\Helpers;

use CatLab\Assets\Laravel\Models\Asset;
use CatLab\Base\Helpers\StringHelper;
use Illuminate\Foundation\Auth\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class AssetUploader
 * @package CatLab\Assets\Helpers
 */
class AssetUploader
{
    const UPLOAD_FOLDER = 'uploads';

    /**
     * @param UploadedFile $file
     * @param User $user
     * @return Asset
     */
    public function uploadFile(UploadedFile $file, User $user = null)
    {
        $asset = $this->getAssetFromFile($file, $user);
        $this->storeAssetFile($file, $asset);

        return $asset;
    }

    /**
     * @param UploadedFile $file
     * @param User|null $user
     * @return Asset
     */
    public function getAssetFromFile(UploadedFile $file, User $user = null)
    {
        // Create record
        $asset = new Asset([
            'name' => $file->getClientOriginalName(),
            'mimetype' => $this->getMimeType($file),
            'type' => ($this->getAssetType($file)),
            'size' => $file->getSize(),
            'path' => $file->getPath(),
        ]);

        if ($user) {
            $asset->user()->associate($user);
        }

        return $asset;
    }

    /**
     * @param UploadedFile $file
     * @param Asset $asset
     */
    protected function storeAssetFile(UploadedFile $file, Asset $asset)
    {
        $asset->save();

        // Move the file to the proper location
        $asset->path = $this->getUploadFolder() . '/' . $this->getFilePath($asset, $file);

        // Move to final destination
        $reader = fopen($file->getPathname(), 'r');
        $asset->getDisk()->put($asset->path, $reader);
        fclose($reader);

        // Update meta data
        $asset->updateMetaData();
        $asset->save();
    }

    /**
     * @return string
     */
    private function getUploadFolder()
    {
        return self::UPLOAD_FOLDER;
    }

    /**
     * @param Asset $asset
     * @param UploadedFile $file
     * @return string
     */
    private function getFilePath(Asset $asset, UploadedFile $file)
    {
        $id = str_pad($asset->id, 6, '0', STR_PAD_LEFT);

        $extension = $file->getClientOriginalExtension();
        $filename = StringHelper::substr($file->getClientOriginalName(), 0, -(1 + StringHelper::length($extension)));

        return $id
            . '-' . StringHelper::escapeFileName($filename)
            . '.' . $extension;
    }


    /**
     * @param UploadedFile $file
     * @return mixed
     */
    private function getMimeType(UploadedFile $file)
    {
        $extension = $file->getClientOriginalExtension();
        switch ($extension) {
            case 'html':
            case 'xhtml':
            case 'htm':
                return 'text/html';

            // MP3 is often misdetected
            case 'mp3':
                return 'audio/mpeg';

            case 'svg':
                return 'image/svg+xml';
        }

        return $file->getMimeType();
    }

    /**
     * @param UploadedFile $file
     * @return mixed
     */
    private function getAssetType(UploadedFile $file)
    {
        $type = $this->getMimeType($file);
        $parts = explode('/', $type);

        return array_shift($parts);
    }
}