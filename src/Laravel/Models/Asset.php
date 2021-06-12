<?php

namespace CatLab\Assets\Laravel\Models;

use CatLab\Assets\Laravel\Controllers\AssetController;
use CatLab\Assets\Laravel\Helpers\AssetFactory;
use CatLab\Assets\Laravel\Helpers\AssetUploader;
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
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'last_used_at' => 'datetime:Y-m-d',
    ];

    /**
     * @var string
     */
    protected $table = 'assets';

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
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getData()
    {
        return $this->getDisk()->get($this->path);
    }

    /**
     * Return the image path that will be used by Image Intervention
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function getOriginalImage()
    {
        return $this->getDisk()->get($this->path);
    }

    /**
     * Get a resized variation of this image.
     * @param $width
     * @param $height
     * @param null $shape
     * @param null $borderWidth
     * @param null $borderColor
     * @return Asset
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getResizedImage(
        $width,
        $height,
        $shape = null,
        $borderWidth = null,
        $borderColor = null,
        $refresh = false
    ) {
        if (!$this->isImage()) {
            return $this;
        }

        if (!$width || !$height) {
            return $this;
        }

        $width = (int) $width;
        $height = (int) $height;

        $forceEncoding = null;

        // Check for variations of this image with the desired dimensions.

        // Validate border
        if (isset($borderWidth)) {
            $borderWidth = intval($borderWidth);
        }

        if ($borderWidth < 1) {
            $borderWidth = null;
            $borderColor = null;
        } else {
            if (!isset($borderColor) || !$this->isValidColor($borderColor)) {
                $borderColor = '#000';
            }
        }

        $variationName = 'resized:' . $width . ':' . $height;
        if ($shape) {
            $variationName .= ':' . $shape;
        }

        if ($borderWidth) {
            $variationName .= ':bw-' . intval($borderWidth);
        }

        if ($borderColor) {
            $variationName .= ':bc-' . $borderColor;
        }

        // name too long? hash it.
        if (strlen($variationName) > 32) {
            $variationName = md5($variationName);
        }

        $variation = $this->getVariation($variationName, true);

        if ($variation !== null) {

            // Do we want to refresh this image?
            // If so, we need to delete the asset.
            if ($refresh) {
                $variation->delete();
            } else {
                $this->wasCached = true;
                return $variation->asset;
            }
        }

        $this->wasCached = false;

        // Actual resize.
        $image = Image::make($this->getOriginalImage());
        $image = $image->fit($width, $height);

        // Apply a mask
        switch ($shape) {
            case AssetController::QUERY_SHAPE_CIRCLE:

                $maskImage = Image::canvas($width, $height);
                $maskImage->circle($width - 3, ($width / 2), ($height / 2), function ($draw) {
                    $draw->background('#ffffff');
                });

                $image->mask($maskImage, true);
                $forceEncoding = 'png';
                break;
        }

        // should we draw a border?
        if ($borderWidth > 0) {
            $this->drawBorder($image, $shape, $width, $height, $borderWidth, $borderColor);
        }

        if ($forceEncoding) {
            $encoded = $image->encode($forceEncoding);
        } else {
            $encoded = $image->encode();
        }

        // put in temporary file and upload as new asset.
        $tmpFile = tempnam(sys_get_temp_dir(), 'asset');
        file_put_contents($tmpFile, $encoded);

        // Move the file to the new location
        $variation = $this->createVariation($variationName, $tmpFile, true);

        // Remove temporary file
        unlink($tmpFile);

        return $variation->asset;
    }

    /**
     * @param $hex_color
     * @return false|true
     */
    protected function isValidColor($hex_color)
    {
        return preg_match('/^#[a-f0-9]{6}$/i', $hex_color);
    }

    /**
     * @param $name
     * @param bool $shareGlobally TRUE to mark that this asset will always have this variation, whoever owns it.
     * @return Variation|null
     */
    public function getVariation($name, $shareGlobally = false)
    {
        $variation = $this->variations;
        return $variation
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
     * @throws \Exception
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
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
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
     * Calculate a new hash.
     * (This downloads the complete file to the tmp directory, so don't use too often)
     * @return bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getFreshHash()
    {
        $currentDisk = $this->getDisk();

        $tmpFile = tempnam(sys_get_temp_dir(), 'asset');
        $handle = fopen($tmpFile, "w");

        $bh = $currentDisk->readStream($this->path);
        while(!feof($bh)) {
            fwrite($handle, fread($bh, 8192));
        }
        fclose($handle);

        $file = new UploadedFile($tmpFile, $this->name);

        $uploader = new AssetUploader();
        $hash = $uploader->getHash($file);

        // Remove temporary file
        unlink($tmpFile);

        return $hash;
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
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
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
        return $this->belongsTo(AssetFactory::getAssetClassName(), 'root_asset_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function variations()
    {
        return $this
            ->getRootAsset()
            ->hasMany(Variation::class, 'original_asset_id', 'id')
            ->with('asset');
    }

    /**
     * Create and upload a variation.
     * @param $variationName
     * @param $tmpFile
     * @param bool $shareGlobally
     * @return Variation
     * @throws \Exception
     */
    public function createVariation($variationName, $tmpFile, $shareGlobally = false)
    {
        $file = new UploadedFile($tmpFile, $this->name);
        $uploader = new AssetUploader();

        // Create record
        $variationAsset = $uploader->getDuplicate($file);
        if (!$variationAsset) {
            $variationAsset = $uploader->getAssetFromFile($file);

            if ($this->user) {
                $variationAsset->user()->associate($this->user);
            }

            // Also keep the root asset link.
            $variationAsset->rootAsset()->associate($this);

            // Save.
            $variationAsset->save();

            $uploader->storeAssetFile($file, $variationAsset);
        }

        return $this->linkVariation($variationName, $variationAsset, $shareGlobally);

    }

    /**
     * @param $variationName
     * @param Asset $variationAsset
     * @param $shareGlobally
     * @return Variation
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function linkVariation($variationName, Asset $variationAsset, $shareGlobally = false)
    {
        // Check for name
        if (empty($variationAsset->name)) {
            $variationAsset->name = $this->name;
        }

        if (empty($variationAsset->type)) {
            $variationAsset->type = $this->type;
        }

        if (empty($variationAsset->mimetype)) {
            $variationAsset->mimetype = $this->mimetype;
        }

        if (empty($variationAsset->hash)) {
            $variationAsset->hash = $variationAsset->getFreshHash();
        }

        // Is a new asset? Link it to the current asset.
        if (!$variationAsset->exists) {
            if ($this->user) {
                $variationAsset->user()->associate($this->user);
            }

            // Also keep the root asset link.
            $variationAsset->rootAsset()->associate($this);

            // Save.
            $variationAsset->save();
        }

        // create the variation
        $variation = $this->createVariationModel([
            'variation_name' => $variationName
        ], $shareGlobally);

        $variation->asset()->associate($variationAsset);

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

    /**
     * Update the last_used property
     */
    public function updateLastUsed()
    {
        if (
            $this->last_used_at === null ||
            $this->last_used_at < (new \DateTime())->sub(new \DateInterval('P1D'))
        ) {
            $this->last_used_at = new \DateTime();
            $this->save();
        }
    }

    /**
     * @param array $attributes
     * @param bool $shareGlobally
     * @return Variation
     */
    protected function createVariationModel(array $attributes, $shareGlobally = false)
    {
        $variation = new Variation($attributes);
        $variation->original()->associate($this);

        return $variation;
    }

    /**
     * @param \Intervention\Image\Image $image
     * @param $shape
     * @param $width
     * @param $height
     * @param $borderWidth
     * @param $borderColor
     */
    protected function drawBorder(
        \Intervention\Image\Image $image,
        $shape,
        $width,
        $height,
        $borderWidth,
        $borderColor
    ) {
        // only circles are supported at the moment.
        if ($shape !== AssetController::QUERY_SHAPE_CIRCLE) {
            return;
        }

        $image->resizeCanvas(
            ceil($width + ($borderWidth / 2)),
            ceil($height + ($borderWidth / 2))
        );

        $borderImage = Image::canvas(
            ($width + $borderWidth),
            ($height + $borderWidth)
        );

        $borderImage->circle(
            $width - $borderWidth,
            floor(($width + $borderWidth * 0.5) / 2),
            floor(($height + $borderWidth * 0.5) / 2),
            function ($draw) use ($borderWidth, $borderColor) {

                $draw->border($borderWidth, $borderColor);

            });

        $image->insert($borderImage);

        // and resize back to the original size.
        $image->resize($width, $height);

    }
}
