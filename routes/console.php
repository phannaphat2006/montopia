<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('monstopia:backup-all')
    ->dailyAt('02:00')
    ->timezone(config('monstopia.business_timezone'))
    ->withoutOverlapping();
