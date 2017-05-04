<?php

namespace CatLab\Assets\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Image;
use Storage;
use Config;

/**
 * Class Asset
 *
 * An asset represents a file stored somewhere.
 *
 * @package CatLab\Assets\Models
 */
class Asset extends Model
{
    const STORAGE_DISK = 'local';

    const CACHE_LIFETIME = 60*60*24*365;

    protected $fillable = [
        'name',
        'mimetype',
        'type',
        'size',
        'path',
    ];

    /**
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(Config::get('assets.userClassName'));
    }

    /**
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function getDisk()
    {
        return Storage::disk(Asset::STORAGE_DISK);
    }

    /**
     *
     */
    public function updateMetaData()
    {
        if ($this->type === 'image') {
            $disk = $this->getDisk();
            if (!$disk->exists($this->path)) {
                return;
            }

            $tmpfile = sys_get_temp_dir() . '/' . uniqid('image');

            file_put_contents($tmpfile, $disk->get($this->path));
            $imagesize = getimagesize($tmpfile);
            unlink($tmpfile);

            if ($imagesize) {
                $this->width = $imagesize[0];
                $this->height = $imagesize[1];
            }
        }
    }

    /**
     * @return bool
     */
    public function isImage() : bool
    {
        return $this->type == 'image';
    }

    /**
     * @return bool
     */
    public function isAudio()
    {
        switch ($this->mimetype) {
            case 'audio/mp3':
            case 'audio/mpeg':
                return true;

            default:
                return false;
        }
    }

    /**
     * @return bool
     */
    public function isVideo()
    {
        switch ($this->mimetype) {
            case 'video/mp4':
            case 'video/mpeg':

                return true;

            default:
                return false;
        }
    }

    /**
     * @return bool
     */
    public function isDocument()
    {
        switch ($this->mimetype) {
            case 'application/octet-stream':
                return false;

            default:
                return true;
        }
    }

    /**
     * @return bool
     */
    public function isPdf()
    {
        switch ($this->mimetype) {
            case 'application/pdf':
            case 'application/x-pdf':
            case 'application/acrobat':
            case 'applications/vnd.pdf':
            case 'text/pdf':
            case 'text/x-pdf':
                return false;

            default:
                return true;
        }
    }

    /**
     * @return bool
     */
    public function isSvg()
    {
        return $this->mimetype == 'image/svg+xml';
    }

    /**
     * @param array $parameters Optionally define properties that will be sent to the controller.
     * @return string
     */
    public function getUrl($parameters = []) : string
    {
        $parameters['id'] = $this;
        return action('\Epyc\Assets\Controllers\AssetController@view', $parameters);
    }

    /**
     * Get raw data.
     * @return string
     */
    public function getData()
    {
        return $this->getDisk()->get($this->path);
    }

    /**
     * Return the image path that will be used by Image Intervention
     * @TODO FIX THIS
     * @return string
     */
    private function getOriginalImage()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'imgToResize');
        file_put_contents($tmpFile, $this->getDisk()->get($this->path));

        return $tmpFile;

        return $this->getDisk()->get($this->path);
    }

    /**
     * @param $width
     * @param $height
     * @return string
     */
    public function getResizedImage($width, $height)
    {
        if (!$this->isImage()) {
            return $this->getData();
        }

        if (!$width || !$height) {
            return $this->getData();
        }

        return Image::cache(
            function($image) use ($width, $height) {
                return $image
                    ->make($this->getOriginalImage())
                    ->fit($width, $height);
            }, self::CACHE_LIFETIME
        );
    }

    /**
     * Return (estimated?) dimensions
     * @return array|null
     */
    public function getDimensions()
    {
        if ($this->isImage()) {
            return [ $this->width, $this->height ];
        }
        return null;
    }

    /**
     * Delete the asset.
     *
     * @param bool $deleteFileFromDisk When true, the associated file is also deleted from disk.
     */
    public function delete($deleteFileFromDisk = true)
    {
        if ($deleteFileFromDisk && $this->existsOnDisk()) {
            $check = $this->deleteFromDisk();
        }

        return parent::delete();
    }

    /**
     * Check if the file associated with this asset exists on disk.
     *
     * @return bool
     */
    public function existsOnDisk()
    {
        $disk = $this->getDisk();
        return $disk->exists($this->path);
    }

    /**
     * Delete the file associated with this asset from disk
     *
     * @return bool
     */
    public function deleteFromDisk()
    {
        $disk = $this->getDisk();
        return $disk->delete($this->path);
    }
}
