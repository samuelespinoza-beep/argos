<?php

namespace App\Console\Commands;

use App\Scrapers\ArchivoScraper;
use App\Services\RegistrarOfertas;
use Illuminate\Console\Command;

class ImportarCommand extends Command
{
    protected $signature = 'argos:importar
        {sitio : Clave del sitio (ej. ofertasimple)}
        {archivo : JSON con la lista de ofertas normalizadas}';

    protected $description = 'Carga un JSON de ofertas (analisis puntual de una pagina) como la foto del dia de ese sitio';

    public function handle(RegistrarOfertas $registrar): int
    {
        $sitio = $this->argument('sitio');
        $archivo = $this->argument('archivo');

        if (!preg_match('/^[a-z0-9-]{2,40}$/', $sitio)) {
            $this->error('El sitio debe ser una clave corta en minusculas (letras, numeros y guiones).');

            return self::FAILURE;
        }
        if (!is_file($archivo)) {
            $this->error("No existe el archivo {$archivo}");

            return self::FAILURE;
        }

        $corrida = $registrar->ejecutar(new ArchivoScraper($sitio, $archivo));

        $this->line(sprintf(
            '%s · %s · %d ofertas (%d nuevas, %d retiradas) · %d errores',
            $sitio,
            $corrida->estado,
            $corrida->ofertas_encontradas,
            $corrida->ofertas_nuevas,
            $corrida->ofertas_retiradas,
            $corrida->errores,
        ));

        if ($corrida->estado !== 'ok') {
            $this->error($corrida->mensaje);

            return self::FAILURE;
        }
        if ($corrida->errores > 0) {
            $this->warn('Hubo ofertas que no se guardaron: el detalle esta en storage/logs/laravel.log');
        }

        return self::SUCCESS;
    }
}
