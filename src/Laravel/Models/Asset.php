<?php

namespace CatLab\Assets\Laravel\Models;

use CatLab\Assets\Laravel\Controllers\AssetController;
use CatLab\Assets\Laravel\Helpers\AssetUploader;
use CatLab\Assets\Laravel\Helpers\Cache;
use CatLab\Assets\Laravel\PathGenerators\PathGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;

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
     * Get a resized variation of this image.
     * @param $width
     * @param $height
     * @return Asset
     */
    public function getResizedImage($width, $height)
    {
        if (!$this->isImage()) {
            return $this;
        }

        if (!$width || !$height) {
            return $this;
        }

        $width = (int) $width;
        $height = (int) $height;

        // Check for variations of this image with the desired dimensions.

        $variationName = 'resized:' . $width . ':' . $height;
        $variation = $this->getVariation($variationName);

        if ($variation !== null) {
            $this->wasCached = true;
            return $variation->variation;
        }

        $this->wasCached = false;

        // Actual resize.
        $image = Image::make($this->getOriginalImage());
        $image = $image->fit($width, $height);

        $encoded = $image->encode();

        // put in temporary file and upload as new asset.
        $tmpFile = tempnam(sys_get_temp_dir(), 'asset');
        file_put_contents($tmpFile, $encoded);

        // Move the file to the new location
        $variation = $this->createVariation($variationName, $tmpFile);

        // Remove temporary file
        unlink($tmpFile);

        return $variation->variation;
    }

    /**
     * @param $name
     * @return Variation|null
     */
    public function getVariation($name)
    {
        return $this
            ->variations
            ->where('variation_name', '=', $name)
            ->first();
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

    /**
     * Move file from one disk to another.
     * @param string $disk
     */
    public function moveToDisk($disk)
    {
        $currentDisk = $this->getDisk();
        $targetDisk = Storage::disk($disk);

        $tmpFile = tempnam(sys_get_temp_dir(), 'asset');
        $handle = fopen($tmpFile, "w");

        $bh = $currentDisk->readStream($this->path);
        while(!feof($bh)) {
            fwrite($handle, fread($bh, 8192));
        }
        fclose($handle);

        $file = new UploadedFile($tmpFile, $this->name);

        // Move the file to the new location
        $this->disk = $disk;
        $this->path = PathGenerator::getPathGenerator()->generatePath($this, $file);

        // Move to final destination

        // Calculate filesize
        $filesize = filesize($tmpFile);

        // Open reader
        $reader = fopen($tmpFile, 'r+');

        $targetDisk->put(
            $this->path,
            $reader,
            [
                'ContentLength' => $filesize
            ]
        );
        fclose($reader);

        // Save new drive and path.
        $this->save();

        // Remove temporary file
        unlink($tmpFile);
    }

    /**
     * Get the "root variation" of this asset.
     * (A root asset has no root_asset_id, while all variations of the asset will have a root_asset_id set.)
     * A variation is, for example, a resized version of the original asset.
     * Note that variations of variations will still use the root asset.
     */
    public function getRootAsset()
    {
        if ($this->rootAsset === null) {
            return $this;
        } else {
            return $this->rootAsset;
        }
    }

    /**
     * @deprecated Don't use! Use getRootAsset to make sure you always get a result.
     * @return BelongsTo
     */
    public function rootAsset()
    {
        return $this->belongsTo(Asset::class, 'root_asset_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function variations()
    {
        return $this->getRootAsset()
            ->hasMany(Variation::class, 'original_asset_id', 'id')
            ->with('variation')
        ;
    }

    /**
     * Create and upload a variation.
     * @param $variationName
     * @param $tmpFile
     * @return Variation
     */
    public function createVariation($variationName, $tmpFile)
    {
        $file = new UploadedFile($tmpFile, $this->name);
        $uploader = new AssetUploader();

        // Create record
        $variationAsset = $uploader->getAssetFromFile($file);
        if ($this->user) {
            $variationAsset->user()->associate($this->user);
        }

        // Also keep the root asset link.
        $variationAsset->rootAsset()->associate($this);

        // Save.
        $variationAsset->save();
        $uploader->storeAssetFile($file, $variationAsset);

        // create the variation
        $variation = new Variation([
            'variation_name' => $variationName
        ]);

        $variation->original()->associate($this);
        $variation->variation()->associate($variationAsset);

        $variation->save();

        return $variation;
    }

    /**
     * Guess the extension of the file.
     * @return mixed
     */
    public function getExtension()
    {
        return pathinfo($this->name, PATHINFO_EXTENSION);
    }
}
