<?php

use App\Http\Controllers\PanelController;
use Illuminate\Support\Facades\Route;

Route::controller(PanelController::class)->group(function () {
    Route::get('/', 'inicio')->name('inicio');
    Route::get('/ofertas/{sitio}/{dia?}', 'ofertas')->name('ofertas')->where('dia', '\d{4}-\d{2}-\d{2}');
    Route::get('/comparar/{competidor?}/{dia?}', 'comparar')->name('comparar')->where('dia', '\d{4}-\d{2}-\d{2}');
});
