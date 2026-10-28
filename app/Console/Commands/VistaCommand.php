<?php

namespace App\Console\Commands;

use App\Services\FotoOfertas;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class VistaCommand extends Command
{
    protected $signature = 'argos:vista
        {sitio : Sitio a mostrar (ej. cuponatic, cuponidad)}
        {--fecha= : Foto a mostrar, AAAA-MM-DD (por defecto la mas reciente)}
        {--contra= : Foto con la que comparar, AAAA-MM-DD (por defecto la anterior)}
        {--carpeta= : Carpeta de salida (por defecto storage/analisis/<fecha>-<sitio>)}';

    protected $description = 'Genera un HTML con filtros de las ofertas de un sitio y sus cambios respecto a la foto anterior';

    public function handle(FotoOfertas $foto): int
    {
        $sitio = $this->argument('sitio');

        try {
            $datos = $foto->armar($sitio, $this->option('fecha'), $this->option('contra'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $fecha = $datos['dia'];
        $contra = $datos['dia_ant'];

        $html = strtr(file_get_contents(resource_path('argos/vista-ofertas.html')), [
            '__DATOS__' => json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG),
            '__SITIO__' => e(ucfirst($sitio)),
            '__TITULO__' => e(ucfirst($sitio) . ' – Ofertas ' . Carbon::parse($fecha)->format('d/m/Y')),
            '__DESDE__' => $datos['desde'],
        ]);

        $carpeta = $this->option('carpeta')
            ?: storage_path('analisis/' . str_replace('-', '', $fecha) . "-{$sitio}");
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0775, true);
        }
        $archivo = "{$carpeta}/ofertas-{$sitio}.html";
        file_put_contents($archivo, $html);

        $ofertas = collect($datos['ofertas']);
        $this->line(sprintf(
            '%s · foto del %s%s · %d ofertas',
            $sitio,
            $fecha,
            $contra ? " vs {$contra}" : ' (sin foto anterior)',
            $ofertas->count(),
        ));
        if ($contra) {
            $this->line(sprintf(
                '  +%d vendidos (≈ S/ %s) · %d nuevas · %d retiradas · %d cambios de precio',
                $ofertas->sum('delta_vend'),
                number_format($ofertas->sum('ingreso'), 2),
                $ofertas->where('nueva', true)->count(),
                count($datos['retiradas']),
                $ofertas->whereNotNull('delta_precio')->count(),
            ));
        }
        $this->info("HTML: {$archivo}");

        return self::SUCCESS;
    }
}
