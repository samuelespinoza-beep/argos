<?php

namespace App\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cuponatic Peru: API JSON publica de listados (la que usa su web), una
 * llamada por categoria. No necesita sesion.
 *
 * Las valoraciones "del comercio" solo estan en el HTML de cada oferta y
 * siempre dicen 3 (dato poco confiable), por eso no se leen: solo se guardan
 * las valoraciones del producto que trae la API.
 */
class CuponaticScraper implements Scraper
{
    public function sitio(): string
    {
        return 'cuponatic';
    }

    public function ofertas(): iterable
    {
        $config = config('argos.sitios.cuponatic');
        $porId = [];

        foreach ($config['categorias'] as $categoria) {
            $lista = Http::withHeaders(['User-Agent' => $config['user_agent']])
                ->timeout(90)
                ->retry(3, 3000)
                ->get("{$config['api']}/{$categoria}", [
                    'ciudad' => $config['ciudad'],
                    'v' => 26,
                    'limit' => $config['limit'],
                ])
                ->throw()
                ->json();

            if (!is_array($lista)) {
                throw new \RuntimeException("Cuponatic: respuesta inesperada en la categoria {$categoria}");
            }
            if (count($lista) >= $config['limit']) {
                Log::warning("Cuponatic: {$categoria} llego al limite de {$config['limit']}, puede haber mas ofertas");
            }

            // Una oferta puede estar en varias categorias: se guarda una vez.
            foreach ($lista as $d) {
                $id = (string) $d['id_descuento'];
                if (isset($porId[$id])) {
                    $porId[$id]['extra']['categorias'][] = $categoria;
                    continue;
                }
                $porId[$id] = $this->normalizar($d, $categoria);
            }

            sleep($config['pausa_segundos']);
        }

        return array_values($porId);
    }

    private function normalizar(array $d, string $categoria): array
    {
        $precio = $this->monto($d['valor_oferta_plano'] ?? $d['valor_oferta'] ?? null);
        $normal = $this->monto($d['valor_original'] ?? null);
        $calificacion = $this->monto($d['calificaciones_en_numero'] ?? null); // escala 0-10

        return [
            'external_id' => (string) $d['id_descuento'],
            'titulo' => trim($d['titulo']),
            'marca' => $d['marcas'] ?: null,
            'categoria' => $categoria,
            'subcategoria' => $d['menu_label'] ?? null,
            'tipo' => $d['tipo_maqueta'] ?? null,
            'url' => $d['url_desktop'],
            'imagen' => $d['imagen'] ?? null,
            'precio_oferta' => $precio,
            'precio_normal' => $normal,
            'descuento_pct' => $d['porcentaje_descuento'] ?: null,
            'vendidos' => $d['cvend_num'] ?? null,
            'valoraciones' => isset($d['cantidad_calificaciones']) ? (int) $d['cantidad_calificaciones'] : null,
            'estrellas' => $calificacion !== null ? round($calificacion / 2, 1) : null,
            'stock_maximo' => $d['maximo'] ?? null,
            'estado' => $d['estado_venta'] ?? null,
            'fecha_termino' => isset($d['fecha_termino']) ? substr($d['fecha_termino'], 0, 10) : null,
            'extra' => [
                'categorias' => [$categoria],
                'vencido' => $d['vencido'] ?? null,
                'tipo_descuento' => $d['tipo_descuento'] ?? null,
                'ultimas_unidades' => $d['alerta_ultimas_unidades'] ?? null,
            ],
        ];
    }

    private function monto(mixed $valor): ?float
    {
        if ($valor === null || $valor === '' || $valor === false) {
            return null;
        }

        $limpio = preg_replace('/[^\d.]/', '', (string) $valor);

        return $limpio === '' ? null : (float) $limpio;
    }
}
