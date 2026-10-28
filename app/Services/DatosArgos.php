<?php

namespace App\Services;

use App\Models\Corrida;
use Illuminate\Support\Facades\Cache;

/**
 * Datos de las paginas del panel, con cache. La foto de un dia no cambia una vez
 * tomada, asi que se calcula una vez y las siguientes visitas no tocan la base.
 * argos:scrape llama a olvidar() al terminar para que se vea la foto nueva.
 */
class DatosArgos
{
    private const DURACION = 60 * 60 * 24 * 7;

    public function __construct(private FotoOfertas $foto, private CruceOfertas $cruce) {}

    public function indice(): array
    {
        return Cache::remember('argos:indice', self::DURACION, function () {
            $sitios = collect(config('argos.sitios'))->keys()
                ->mapWithKeys(fn ($sitio) => [$sitio => $this->foto->fechas($sitio)->all()])
                ->filter();
            $propias = collect($sitios[CruceOfertas::PROPIO] ?? []);

            return [
                'actualizado' => Corrida::whereNotNull('terminada_at')->latest('terminada_at')->first()?->terminada_at->toIso8601ZuluString(),
                'propio' => CruceOfertas::PROPIO,
                'sitios' => $sitios->all(),
                'competidores' => $sitios->except(CruceOfertas::PROPIO)
                    ->map(fn ($fechas) => $propias->intersect($fechas)->values()->all())
                    ->filter()->all(),
            ];
        });
    }

    public function ofertas(string $sitio, string $fecha): array
    {
        return Cache::remember("argos:ofertas:{$sitio}:{$fecha}", self::DURACION, fn () => $this->foto->armar($sitio, $fecha));
    }

    public function cruce(string $competidor, string $fecha): array
    {
        return Cache::remember("argos:cruce:{$competidor}:{$fecha}", self::DURACION, fn () => $this->cruce->armar($competidor, $fecha));
    }

    public function olvidar(array $fechas): void
    {
        Cache::forget('argos:indice');
        $competidores = array_diff(array_keys(config('argos.sitios')), [CruceOfertas::PROPIO]);
        foreach ($fechas as $fecha) {
            foreach (array_keys(config('argos.sitios')) as $sitio) {
                Cache::forget("argos:ofertas:{$sitio}:{$fecha}");
            }
            foreach ($competidores as $competidor) {
                Cache::forget("argos:cruce:{$competidor}:{$fecha}");
            }
        }
    }
}
