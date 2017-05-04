<?php

namespace CatLab\Assets\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

/**
 * Class Assetable
 * @package CatLab\Assets\Models
 */
abstract class Assetable extends Model
{
    /**
     * Statements that should be included in the migration for a table that
     * needs a link with assets.
     *
     * Usage:
     * - Include this model (use Epyc\Assets\Models\Assetable;)
     * - In the Schema::create() function, include this function
     *      (Assetable::column($table)), where $table is the instance of blueprint
     *      used in that migration
     *
     * @param Blueprint $table
     * @param string $column
     * @return string
     */
    public static function column(Blueprint $table, $column = 'asset_id')
    {
        $col = $table->integer($column)->unsigned();
        $table->foreign($column)->references('id')->on('assets');

        return $col;
    }
}