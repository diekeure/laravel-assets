<?php

namespace CatLab\Assets\Laravel\Models;

use CatLab\Assets\Laravel\Helpers\AssetFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProcessorJob;

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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function processorJob()
    {
        return $this->belongsTo(ProcessorJob::class);
    }
}