<?php

namespace App\Http\Controllers;

use App\Services\CruceOfertas;
use App\Services\DatosArgos;
use Inertia\Inertia;
use Inertia\Response;

class PanelController extends Controller
{
    public function __construct(private DatosArgos $datos) {}

    public function inicio(): Response
    {
        return Inertia::render('Inicio', ['indice' => $this->datos->indice()]);
    }

    public function ofertas(string $sitio, ?string $dia = null): Response
    {
        abort_unless(array_key_exists($sitio, config('argos.sitios')), 404);

        $fechas = $this->datos->indice()['sitios'][$sitio] ?? [];
        $dia ??= $fechas[0] ?? null;
        abort_if($dia && !in_array($dia, $fechas, true), 404);

        return Inertia::render('Ofertas', [
            'sitio' => $sitio,
            'fechas' => $fechas,
            'foto' => $dia ? $this->comoObjeto($this->datos->ofertas($sitio, $dia)) : null,
        ]);
    }

    public function comparar(?string $competidor = null, ?string $dia = null): Response
    {
        $competidores = $this->datos->indice()['competidores'];
        $competidor ??= array_key_first($competidores) ?? array_key_first(array_diff_key(config('argos.sitios'), [CruceOfertas::PROPIO => 1]));
        abort_unless($competidor !== CruceOfertas::PROPIO && array_key_exists($competidor, config('argos.sitios')), 404);

        $fechas = $competidores[$competidor] ?? [];
        $dia ??= $fechas[0] ?? null;
        abort_if($dia && !in_array($dia, $fechas, true), 404);

        return Inertia::render('Comparar', [
            'competidor' => $competidor,
            'fechas' => $fechas,
            'cruce' => $dia ? $this->comoObjeto($this->datos->cruce($competidor, $dia)) : null,
        ]);
    }

    /**
     * Inertia recorre clave por clave todo prop que sea un arreglo: con ~1,100 ofertas
     * eso tardaba ~1.6 s por visita. Como objeto (stdClass) pasa directo al JSON (~10 ms).
     */
    private function comoObjeto(array $datos): object
    {
        return json_decode(json_encode($datos));
    }
}
