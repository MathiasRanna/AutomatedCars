<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule cleanup of old auction images to run daily at 2 AM
Schedule::command('auctions:cleanup-old-images')
    ->dailyAt('02:00')
    ->description('Clean up auction images older than 14 days');
