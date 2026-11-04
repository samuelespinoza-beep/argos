<?php

use App\Http\Controllers\PanelController;
use Illuminate\Support\Facades\Route;

Route::controller(PanelController::class)->group(function () {
    Route::get('/', 'inicio')->name('inicio');
    Route::get('/ofertas/{sitio}', 'ofertas')->name('ofertas');
    Route::get('/comparar/{competidor?}', 'comparar')->name('comparar');
});
