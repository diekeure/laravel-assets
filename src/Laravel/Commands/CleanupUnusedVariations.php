<?php

namespace CatLab\Assets\Laravel\Commands;

use CatLab\Assets\Laravel\Models\Variation;
use Illuminate\Console\Command;

/**
 * Class CleanupUnusedVariations
 * @package CatLab\Assets\Laravel\Commands
 */
class CleanupUnusedVariations extends Command
{
    /**
     * The duration (age) of variations that will be marked as 'unused'
     */
    const UNUSED_VARIATION_AGE = 'P15D';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'variations:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup (remove) all variations that are not often used and can easily be generated again.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Variation
            ::whereNull('processor_id')
            ->select('variations.*')
            ->leftJoin('assets', 'variations.variation_asset_id', '=', 'assets.id')
            ->whereDate(
                'assets.last_used_at',
                '<',
                (new \DateTime())->sub(new \DateInterval(self::UNUSED_VARIATION_AGE))
            )->orWhereNull('assets.last_used_at')
            ->orderBy('assets.last_used_at')
            ->orderBy('assets.id')
            ->chunk(100, function($variations) {

                foreach ($variations as $variation) {
                    /** @var Variation $variation */

                    $debugMessage = 'Removing variation ' . $variation->id . ', ';
                    if ($variation->asset->last_used_at) {
                        $debugMessage .= 'last used at ' . $variation->asset->last_used_at->format('Y-m-d') . '.';
                    } else {
                        $debugMessage .= 'last used a very long time ago.';
                    }
                    $this->output->writeln($debugMessage);
                    $variation->delete();
                }
            });

        return 0;
    }
}

