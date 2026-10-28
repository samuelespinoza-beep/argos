<?php

namespace App\Http\Controllers;

use App\Services\CruceOfertas;
use App\Services\DatosArgos;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paginas del panel (Inertia): cada metodo elige el componente Vue de
 * resources/js/pages y le pasa los datos como props. Los datos salen de
 * Postgres con cache en Redis (DatosArgos).
 */
class PanelController extends Controller
{
    public function __construct(private DatosArgos $datos) {}

    public function inicio(): Response
    {
        return Inertia::render('Inicio', ['indice' => $this->datos->indice()]);
    }

    /** /ofertas/{sitio}/{dia?}: sin dia, la foto mas reciente. */
    public function ofertas(string $sitio, ?string $dia = null): Response
    {
        abort_unless(array_key_exists($sitio, config('argos.sitios')), 404);

        $fechas = $this->datos->indice()['sitios'][$sitio] ?? [];
        $dia ??= $fechas[0] ?? null;
        abort_if($dia && !in_array($dia, $fechas, true), 404);

        return Inertia::render('Ofertas', [
            'sitio' => $sitio,
            'fechas' => $fechas,
            // null = el sitio aun no tiene fotos (la pagina muestra el aviso).
            'foto' => $dia ? $this->comoObjeto($this->datos->ofertas($sitio, $dia)) : null,
        ]);
    }

    /** /comparar/{competidor?}/{dia?}: sin competidor, el primero; sin dia, el mas reciente en comun. */
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
            // null = no hay fechas con foto de ambos sitios.
            'cruce' => $dia ? $this->comoObjeto($this->datos->cruce($competidor, $dia)) : null,
        ]);
    }

    /**
     * Inertia recorre clave por clave todo prop que sea un arreglo (busca props diferidas,
     * opcionales, etc.): con ~1,100 ofertas x 30 campos eso tardaba ~1.6 s por visita.
     * Como objeto (stdClass) lo deja pasar tal cual al JSON: ~10 ms, mismo resultado.
     */
    private function comoObjeto(array $datos): object
    {
        return json_decode(json_encode($datos));
    }
}
