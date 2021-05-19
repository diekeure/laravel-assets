<?php

namespace CatLab\Assets\Laravel\Models;

use CatLab\Assets\Laravel\Helpers\AssetFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Variation
 * @package CatLab\Assets\Laravel\Models
 */
class Variation extends Model
{
    protected $fillable = [
        'variation_name'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function original()
    {
        return $this->belongsTo(AssetFactory::getAssetClassName(), 'original_asset_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function asset()
    {
        return $this->belongsTo(AssetFactory::getAssetClassName(), 'variation_asset_id');
    }

    /**
     * @return bool|null
     * @throws \Exception
     */
    public function delete()
    {
        // Remember our asset
        $asset = $this->asset;

        // First remove the variation
        $result = parent::delete();

        // Check if we can delete the related asset as well
        if (Variation::where('variation_asset_id', '=', $asset->id)->count() === 0) {
            $asset->delete();
        }

        return $result;
    }
}
