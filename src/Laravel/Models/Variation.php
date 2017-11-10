<?php

namespace CatLab\Assets\Laravel\Models;

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
        return $this->belongsTo(Asset::class, 'original_asset_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function variation()
    {
        return $this->belongsTo(Asset::class, 'variation_asset_id');
    }
}