<?php

namespace App\Console\Commands;

use App\Models\Oferta;
use App\Services\ComparacionCompetitiva;
use Illuminate\Console\Command;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Writer;

class ExportarCommand extends Command
{
    protected $signature = 'argos:exportar
        {competidor : Sitio a comparar con Cuponidad (ej. cuponatic)}
        {--dias=7 : Periodo para las ventas reales}
        {--carpeta= : Carpeta de salida (por defecto storage/analisis/<fecha>-<competidor>)}';

    protected $description = 'Exporta la comparacion Cuponidad vs un competidor a Excel (una hoja por tabla) y CSV';

    public function handle(): int
    {
        $competidor = $this->argument('competidor');

        if (!Oferta::where('sitio', $competidor)->exists()) {
            $this->error("No hay ofertas de \"{$competidor}\" en la base. Scrapea o importa ese sitio primero.");

            return self::FAILURE;
        }

        $comparacion = new ComparacionCompetitiva($competidor);
        $dias = (int) $this->option('dias');

        $hojas = [
            'Historial' => $comparacion->historial(),
            'Categorias' => $comparacion->categorias(),
            'Marcas' => $comparacion->marcas(),
            'Marcas parecidas' => $comparacion->marcasParecidas(),
            'Precios' => $comparacion->precios(),
            "Ventas {$dias} dias" => $comparacion->ventas($dias),
            'Ofertas' => $comparacion->ofertas(),
        ];

        $carpeta = $this->option('carpeta')
            ?: storage_path('analisis/' . now(config('argos.timezone'))->format('Ymd') . "-{$competidor}");
        $csv = "{$carpeta}/csv";
        if (!is_dir($csv)) {
            mkdir($csv, 0775, true);
        }

        $excel = "{$carpeta}/cuponidad-vs-{$competidor}.xlsx";
        $this->escribirExcel($excel, $hojas);

        foreach ($hojas as $nombre => $filas) {
            $this->escribirCsv("{$csv}/" . str($nombre)->slug() . '.csv', $filas);
        }

        $this->table(['Hoja', 'Filas'], collect($hojas)->map(fn ($f, $n) => [$n, count($f)])->values());
        $this->info("Excel: {$excel}");
        $this->info("CSV:   {$csv}/");

        return self::SUCCESS;
    }

    private function escribirExcel(string $archivo, array $hojas): void
    {
        $encabezado = new Style(fontBold: true, fontColor: 'FFFFFF', backgroundColor: '1F4E79');
        $writer = new Writer();
        $writer->openToFile($archivo);

        $primera = true;
        foreach ($hojas as $nombre => $filas) {
            $hoja = $primera ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $hoja->setName($nombre);
            $primera = false;

            if (!$filas) {
                $writer->addRow(Row::fromValues(['Sin datos (las ventas necesitan al menos 2 dias de historial)']));
                continue;
            }

            $columnas = array_keys($filas[0]);
            $writer->addRow(Row::fromValuesWithStyle($columnas, $encabezado));
            foreach ($filas as $fila) {
                $writer->addRow(Row::fromValues(array_map($this->celda(...), array_values($fila))));
            }

            $hoja->setAutoFilter(new AutoFilter(0, 1, count($columnas) - 1, count($filas) + 1));
            foreach ($columnas as $i => $columna) {
                $hoja->setColumnWidth(match (true) {
                    in_array($columna, ['titulo', 'nuestra_oferta', 'su_oferta']) => 50,
                    in_array($columna, ['marca', 'nuestra', 'suya', 'categorias', 'url', 'nuestra_url', 'su_url']) => 30,
                    default => 16,
                }, $i + 1);
            }
        }

        $writer->close();
    }

    private function escribirCsv(string $archivo, array $filas): void
    {
        $f = fopen($archivo, 'w');
        fwrite($f, "\xEF\xBB\xBF"); // BOM: Excel abre bien las tildes
        if ($filas) {
            fputcsv($f, array_keys($filas[0]), escape: '');
            foreach ($filas as $fila) {
                fputcsv($f, $fila, escape: '');
            }
        }
        fclose($f);
    }

    /** Postgres devuelve los decimales como texto: en Excel deben ser numeros. */
    private function celda(mixed $valor): mixed
    {
        return is_string($valor) && is_numeric($valor) ? $valor + 0 : $valor;
    }
}
