<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DailyOptimize extends Command
{
    protected $signature = 'daily:optimize';
    protected $description = 'Clear and optimize cache daily';

    public function handle()
    {
        Artisan::call('optimize:clear');   // Clears route, view, config, and compiled files
        Artisan::call('optimize');         // Re-optimizes class map
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');

        $this->info('Optimization tasks completed successfully.');
    }
}

