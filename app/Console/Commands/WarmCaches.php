<?php

namespace App\Console\Commands;

use App\Services\CategoryTreeService;
use App\Services\InventorySummaryService;
use Illuminate\Console\Command;

class WarmCaches extends Command
{
    protected $signature = 'cache:warm';

    protected $description = 'Prime the category tree and inventory summary caches after a deploy/seed';

    public function handle(
        CategoryTreeService $categoryTree,
        InventorySummaryService $summary,
    ): int {
        $categoryTree->tree();
        $this->line('Warmed: categories:tree (small, read on nearly every page, changes rarely).');

        $summary->summary();
        $this->line('Warmed: inventory:summary (hot report, invalidated on every stock write).');

        return self::SUCCESS;
    }
}
