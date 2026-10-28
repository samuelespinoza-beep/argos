<?php

namespace App\Services;

use App\Models\Corrida;
use Illuminate\Support\Facades\Cache;

/**
 * Los datos que pide la app Vue (rutas /datos/... de routes/web.php), leidos de
 * Postgres y guardados en cache (Redis, segun CACHE_STORE del .env).
 *
 * La foto de un dia no cambia una vez tomada, asi que cada respuesta se calcula
 * una vez y las siguientes visitas no tocan la base. argos:scrape llama a
 * olvidar() al terminar, para que la foto nueva se vea en la proxima visita.
 */
class DatosArgos
{
    private const DURACION = 60 * 60 * 24 * 7; // 7 dias

    public function __construct(private FotoOfertas $foto, private CruceOfertas $cruce) {}

    /** Sitios y fechas con foto, y competidores con fechas en comun con Cuponidad (para el cruce). */
    public function indice(): array
    {
        return Cache::remember('argos:indice', self::DURACION, function () {
            $sitios = collect(config('argos.sitios'))->keys()
                ->mapWithKeys(fn ($sitio) => [$sitio => $this->foto->fechas($sitio)->all()])
                ->filter();
            $propias = collect($sitios[CruceOfertas::PROPIO] ?? []);

            return [
                // Fin del ultimo scraping (para "Último scraping" en Inicio).
                'actualizado' => Corrida::whereNotNull('terminada_at')->latest('terminada_at')->first()?->terminada_at->toIso8601ZuluString(),
                'propio' => CruceOfertas::PROPIO,
                'sitios' => $sitios->all(),
                'competidores' => $sitios->except(CruceOfertas::PROPIO)
                    ->map(fn ($fechas) => $propias->intersect($fechas)->values()->all())
                    ->filter()->all(),
            ];
        });
    }

    /** Foto de un sitio en una fecha. Lanza InvalidArgumentException si no hay foto. */
    public function ofertas(string $sitio, string $fecha): array
    {
        return Cache::remember("argos:ofertas:{$sitio}:{$fecha}", self::DURACION, fn () => $this->foto->armar($sitio, $fecha));
    }

    /** Ofertas en comun entre Cuponidad y un competidor en una fecha. */
    public function cruce(string $competidor, string $fecha): array
    {
        return Cache::remember("argos:cruce:{$competidor}:{$fecha}", self::DURACION, fn () => $this->cruce->armar($competidor, $fecha));
    }

    /** Borra de la cache el indice y lo de esas fechas (despues de un scraping). */
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
