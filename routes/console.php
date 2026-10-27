<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Foto diaria de todos los sitios (Cuponidad + competencia). Necesita que el
// scheduler este corriendo: contenedor "scheduler" de compose.yaml.
Schedule::command('argos:scrape')
    ->dailyAt(config('argos.hora_diaria'))
    ->timezone(config('argos.timezone'))
    ->withoutOverlapping(120);
