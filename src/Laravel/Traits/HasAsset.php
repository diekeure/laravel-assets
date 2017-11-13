<?php

namespace CatLab\Assets\Laravel\Traits;

use CatLab\Assets\Laravel\Helpers\AssetFactory;
use CatLab\Assets\Laravel\Models\Asset;

/**
 * Trait to include the relation with an asset on you model.
 *
 * @package CatLab\Assets\Traits
 */
trait HasAsset
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function asset()
    {
        return $this->belongsTo(AssetFactory::getAssetClassName());
    }

    /**
     * @param string $linkTextField
     * @return string
     */
    public function getLink($linkTextField = 'label')
    {
        return $this->asset->getUrl();
    }
}
