<?php

namespace App\Http\Controllers;

use App\Services\CruceOfertas;
use App\Services\DatosArgos;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Datos para la app Vue. Cada metodo responde JSON leido de Postgres (con cache
 * en Redis) a traves de DatosArgos; ya no se generan archivos.
 */
class DatosController extends Controller
{
    public function __construct(private DatosArgos $datos) {}

    /** GET /datos/indice */
    public function indice(): JsonResponse
    {
        return response()->json($this->datos->indice());
    }

    /** GET /datos/ofertas/{sitio}/{fecha} */
    public function ofertas(string $sitio, string $fecha): JsonResponse
    {
        if (!array_key_exists($sitio, config('argos.sitios'))) {
            return $this->noEncontrado("Sitio desconocido: {$sitio}");
        }

        return $this->responder(fn () => $this->datos->ofertas($sitio, $fecha));
    }

    /** GET /datos/cruce/{competidor}/{fecha} */
    public function cruce(string $competidor, string $fecha): JsonResponse
    {
        if ($competidor === CruceOfertas::PROPIO || !array_key_exists($competidor, config('argos.sitios'))) {
            return $this->noEncontrado("Competidor desconocido: {$competidor}");
        }

        return $this->responder(fn () => $this->datos->cruce($competidor, $fecha));
    }

    /** Si no hay foto de esa fecha, FotoOfertas lanza InvalidArgumentException: se responde 404 con el motivo. */
    private function responder(callable $armar): JsonResponse
    {
        try {
            return response()->json($armar());
        } catch (InvalidArgumentException $e) {
            return $this->noEncontrado($e->getMessage());
        }
    }

    private function noEncontrado(string $mensaje): JsonResponse
    {
        return response()->json(['error' => $mensaje], 404);
    }
}
