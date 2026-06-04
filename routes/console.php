<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
| Ensure OS scheduler executes: php artisan schedule:run   every 1 minute.
|
| Windows Task Scheduler:
|   Program:   php.exe
|   Arguments: D:\2026\supermarket-pos\artisan schedule:run
|   Trigger:   Repeat every 1 minute, indefinitely
|
*/
Schedule::command('backup:create --notify')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/backup.log'));
