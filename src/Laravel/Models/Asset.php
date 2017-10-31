<?php

namespace CatLab\Assets\Laravel\Models;

use CatLab\Assets\Laravel\Controllers\AssetController;
use CatLab\Assets\Laravel\Helpers\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Image;
use Storage;
use Config;
use Symfony\Component\HttpFoundation\File\File;

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

    /**
     * @var bool
     */
    private $wasCached = null;

    protected $fillable = [
        'name',
        'mimetype',
        'type',
        'size',
        'path',
        'hash',
        'disk'
    ];

    /**
     * Get the default disk that will be used for storing the asset.
     * @return string
     */
    public static function getDefaultDisk()
    {
        return Config::get('assets.disk', self::STORAGE_DISK);
    }

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
        if (!empty($this->disk)) {
            $disk = $this->disk;
        } else {
            $disk = self::getDefaultDisk();
        }

        return Storage::disk($disk);
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
        return action(AssetController::class . '@view', $parameters);
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
     * @return string
     */
    private function getOriginalImage()
    {
        return $this->getDisk()->get($this->path);
    }

    /**
     * @param $width
     * @param $height
     * @param bool $cache
     * @return string
     */
    public function getResizedImage($width, $height, $cache = true)
    {
        if (!$this->isImage()) {
            return $this->getData();
        }

        if (!$width || !$height) {
            return $this->getData();
        }

        $cacheIn = Cache::instance();

        // Checksum = just the query string
        if ($cache) {
            $cachePrefix = config('assets.cachePrefix', 'image:');
            $cacheKey = $cachePrefix . ':resize:' . $this->id . ':' . $width . ':' . $height;


            // check if we have a cached value
            if ($cacheIn->has($cacheKey)) {
                $this->wasCached = true;
                return $cacheIn->get($cacheKey);
            }
        }

        $this->wasCached = false;

        // Actual resize.
        $image = Image::make($this->getOriginalImage());
        $image = $image->fit($width, $height);

        $encoded = $image->encode();

        // Set to code.
        if ($cache) {
            $lifetime = config('assets.cacheLifetime');
            $cacheIn->put($cacheKey, $encoded, $lifetime);
        }

        return $encoded;
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
     * Delete the asset
     * @param bool $deleteFileFromDisk
     * @return bool|null
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

    /**
     * Check if provided file matches the asset file
     * @param File $file
     * @return bool
     */
    public function isFileEqual(File $file)
    {
        $a = $file->getPathname();

        $disk = $this->getDisk();
        $bh = $disk->readStream($this->path);

        // Check if filesize is different
        if(filesize($a) !== $this->size) {
            return false;
        }

        // Check if content is different
        $ah = fopen($a, 'rb');

        $result = true;
        while(!feof($ah)) {
            if(fread($ah, 8192) != fread($bh, 8192))
            {
                $result = false;
                break;
            }
        }

        fclose($ah);
        fclose($bh);

        return $result;
    }

    /**
     * Return data that could be publicly exposed
     * @return array
     */
    public function getMetaData()
    {
        return [
            'type' => $this->type,
            'mimetype' => $this->mimetype,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height
        ];
    }

    /**
     * @return bool
     */
    public function wasCached()
    {
        return $this->wasCached;
    }
}
