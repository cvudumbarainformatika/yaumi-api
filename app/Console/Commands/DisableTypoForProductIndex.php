<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Meilisearch\Client;

class DisableTypoForProductIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meili:disable-typo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disable typo tolerance for the Product index in Meilisearch';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));

        $indexName = (new \App\Models\Product)->searchableAs();

        $client->index($indexName)->updateSettings([
            'typoTolerance' => [
                'enabled' => false,
            ],
        ]);

        $this->info("❌ Typo tolerance disabled for Meilisearch index: {$indexName}");
    }
}
