<?php

use App\Http\Controllers\DatosController;
use Illuminate\Support\Facades\Route;

// Datos para la app Vue (responden JSON, leidos de Postgres con cache en Redis).
// Van ANTES de la ruta de la app para que no los capture.
Route::prefix('datos')->controller(DatosController::class)->group(function () {
    Route::get('indice', 'indice');
    Route::get('ofertas/{sitio}/{fecha}', 'ofertas')->where('fecha', '\d{4}-\d{2}-\d{2}');
    Route::get('cruce/{competidor}/{fecha}', 'cruce')->where('fecha', '\d{4}-\d{2}-\d{2}');
});

// La app Vue: cualquier otra URL devuelve la misma vista y vue-router elige la pagina.
// Se excluyen /datos, /storage y /build para que algo que no existe de un 404 y no
// el HTML de la app.
Route::view('/{any?}', 'app')->where('any', '^(?!datos/|storage/|build/).*$');
