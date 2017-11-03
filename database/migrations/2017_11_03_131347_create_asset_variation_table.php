<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAssetVariationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('assets', function(Blueprint $table) {

            $table->integer('root_asset_id')->unsigned()->nullable()->after('id');
            $table->foreign('root_asset_id')->references('id')->on('assets');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('assets', function(Blueprint $table) {

            $table->dropForeign('assets_root_asset_id_foreign');
            $table->dropColumn('root_asset_id');

        });
    }
}
