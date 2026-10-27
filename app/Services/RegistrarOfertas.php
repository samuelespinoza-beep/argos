<?php

namespace App\Services;

use App\Models\Corrida;
use App\Models\Oferta;
use App\Models\OfertaSnapshot;
use App\Scrapers\Scraper;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ejecuta un scraper y guarda el resultado: actualiza la ficha de cada oferta,
 * graba la foto del dia (oferta_snapshots) y marca como retiradas las ofertas
 * que ya no aparecen. Todo queda registrado en una fila de corridas.
 */
class RegistrarOfertas
{
    /**
     * Si una corrida encuentra menos de esta fraccion de las ofertas activas,
     * se asume que el scraping fallo a medias y no se retira ninguna.
     */
    private const MINIMO_PARA_RETIRAR = 0.5;

    public function ejecutar(Scraper $scraper): Corrida
    {
        $sitio = $scraper->sitio();
        $hoy = now(config('argos.timezone'))->toDateString();

        $corrida = Corrida::create([
            'sitio' => $sitio,
            'estado' => 'en_curso',
            'iniciada_at' => now(),
        ]);

        $encontradas = $nuevas = $errores = 0;

        try {
            foreach ($scraper->ofertas() as $datos) {
                try {
                    $nuevas += $this->guardar($sitio, $datos, $hoy, $corrida) ? 1 : 0;
                    $encontradas++;
                } catch (Throwable $e) {
                    $errores++;
                    Log::warning("Argos [{$sitio}] oferta {$datos['external_id']}: {$e->getMessage()}");
                }
            }

            $retiradas = $this->retirarAusentes($sitio, $hoy, $encontradas);

            $corrida->update([
                'estado' => 'ok',
                'terminada_at' => now(),
                'ofertas_encontradas' => $encontradas,
                'ofertas_nuevas' => $nuevas,
                'ofertas_retiradas' => $retiradas,
                'errores' => $errores,
            ]);
        } catch (Throwable $e) {
            $corrida->update([
                'estado' => 'error',
                'terminada_at' => now(),
                'ofertas_encontradas' => $encontradas,
                'ofertas_nuevas' => $nuevas,
                'errores' => $errores + 1,
                'mensaje' => mb_substr($e->getMessage(), 0, 2000),
            ]);
            Log::error("Argos [{$sitio}] corrida {$corrida->id} fallo: {$e->getMessage()}");
        }

        return $corrida;
    }

    /** @return bool true si la oferta es nueva */
    private function guardar(string $sitio, array $d, string $hoy, Corrida $corrida): bool
    {
        $oferta = Oferta::firstOrNew(['sitio' => $sitio, 'external_id' => $d['external_id']]);
        $esNueva = !$oferta->exists;

        $oferta->fill([
            'titulo' => mb_substr($d['titulo'], 0, 500),
            'marca' => $d['marca'],
            'categoria' => $d['categoria'],
            'subcategoria' => $d['subcategoria'],
            'tipo' => $d['tipo'],
            'url' => $d['url'],
            'imagen' => $d['imagen'],
            'activa' => true,
            'ultima_vez_vista' => $hoy,
        ]);
        if ($esNueva) {
            $oferta->primera_vez_vista = $hoy;
        }
        $oferta->save();

        // Si corre dos veces el mismo dia, la foto del dia se actualiza.
        OfertaSnapshot::updateOrCreate(
            ['oferta_id' => $oferta->id, 'fecha' => $hoy],
            [
                'corrida_id' => $corrida->id,
                'precio_oferta' => $d['precio_oferta'],
                'precio_normal' => $d['precio_normal'],
                'descuento_pct' => $d['descuento_pct'],
                'vendidos' => $d['vendidos'],
                'valoraciones' => $d['valoraciones'],
                'estrellas' => $d['estrellas'],
                'stock_maximo' => $d['stock_maximo'],
                'estado' => $d['estado'],
                'fecha_termino' => $d['fecha_termino'],
                'extra' => $d['extra'] ?? null,
            ],
        );

        return $esNueva;
    }

    private function retirarAusentes(string $sitio, string $hoy, int $encontradas): int
    {
        $activas = Oferta::where('sitio', $sitio)->where('activa', true)->count();

        if ($activas > 0 && $encontradas < $activas * self::MINIMO_PARA_RETIRAR) {
            Log::warning("Argos [{$sitio}]: solo {$encontradas} de {$activas} ofertas; no se retira ninguna por precaucion");

            return 0;
        }

        return Oferta::where('sitio', $sitio)
            ->where('activa', true)
            ->whereDate('ultima_vez_vista', '<', $hoy)
            ->update(['activa' => false]);
    }
}
