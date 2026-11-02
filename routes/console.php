<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('argos:scrape')
    ->dailyAt(config('argos.hora_diaria'))
    ->timezone(config('argos.timezone'))
    ->withoutOverlapping(120);
