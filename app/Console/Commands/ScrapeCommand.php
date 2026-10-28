<?php

namespace App\Console\Commands;

use App\Scrapers\Scraper;
use App\Services\DatosArgos;
use App\Services\FotoOfertas;
use App\Services\RegistrarOfertas;
use Illuminate\Console\Command;
use Throwable;

class ScrapeCommand extends Command
{
    protected $signature = 'argos:scrape {sitio?* : Sitios a scrapear (por defecto todos los de config/argos.php)}';

    protected $description = 'Scrapea los sitios configurados y guarda la foto del dia de cada oferta';

    public function handle(RegistrarOfertas $registrar, DatosArgos $datos, FotoOfertas $foto): int
    {
        $configurados = array_keys(config('argos.sitios'));
        $sitios = $this->argument('sitio') ?: $configurados;

        if ($desconocidos = array_diff($sitios, $configurados)) {
            $this->error('Sitio desconocido: ' . implode(', ', $desconocidos) . '. Disponibles: ' . implode(', ', $configurados));

            return self::FAILURE;
        }

        $fallo = false;
        $conFoto = [];
        foreach ($sitios as $sitio) {
            $this->info("Scrapeando {$sitio}...");

            /** @var Scraper $scraper */
            $scraper = app(config("argos.sitios.{$sitio}.scraper"));
            $corrida = $registrar->ejecutar($scraper);

            $this->line(sprintf(
                '  %s · %d ofertas (%d nuevas, %d retiradas) · %d errores · %ds',
                $corrida->estado,
                $corrida->ofertas_encontradas,
                $corrida->ofertas_nuevas,
                $corrida->ofertas_retiradas,
                $corrida->errores,
                $corrida->iniciada_at->diffInSeconds($corrida->terminada_at),
            ));

            if ($corrida->estado !== 'ok') {
                $this->error("  {$corrida->mensaje}");
                $fallo = true;
            }
            if ($corrida->ofertas_encontradas > 0) {
                $conFoto[] = $sitio;
            }
        }

        // La app Vue lee de Postgres con cache (DatosArgos): se borra la cache de la
        // foto de hoy para que la proxima visita traiga los datos nuevos. Va aparte:
        // si falla, la foto del dia ya quedo guardada en la base.
        if ($conFoto) {
            try {
                $datos->olvidar(collect($conFoto)->map(fn ($s) => $foto->fechas($s)->first())->unique()->all());
                $this->info('Cache de la app actualizada.');
            } catch (Throwable $e) {
                report($e);
                $this->error("No se pudo limpiar la cache de la app: {$e->getMessage()}");
            }
        }

        return $fallo ? self::FAILURE : self::SUCCESS;
    }
}
