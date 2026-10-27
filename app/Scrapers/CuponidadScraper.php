<?php

namespace App\Scrapers;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cuponidad (sitio propio): no tiene API JSON. Las ofertas salen del sitemap
 * y los datos de cada pagina de oferta (HTML renderizado por Laravel):
 * JSON-LD Product + BreadcrumbList, precio normal en <del class="cd2-product__old">
 * y "N vendidos". Las estrellas de la pagina son decorativas: no hay valoraciones.
 */
class CuponidadScraper implements Scraper
{
    public function sitio(): string
    {
        return 'cuponidad';
    }

    public function ofertas(): iterable
    {
        $config = config('argos.sitios.cuponidad');
        $urls = $this->urlsDeOfertas($config);

        // Se piden de a pocas paginas a la vez para no cargar el servidor.
        foreach (array_chunk($urls, $config['concurrencia'], true) as $lote) {
            $respuestas = Http::pool(fn (Pool $pool) => array_map(
                fn ($url) => $pool->as($url)->withHeaders(['User-Agent' => $config['user_agent']])->timeout(40)->get($url),
                array_keys($lote),
            ));

            foreach ($lote as $url => $categoriaSitemap) {
                $respuesta = $respuestas[$url] ?? null;
                if (!$respuesta instanceof Response || $respuesta->failed()) {
                    Log::warning("Cuponidad: no se pudo leer {$url}");
                    continue;
                }
                yield $this->normalizar($url, $categoriaSitemap, $respuesta->body());
            }

            usleep($config['pausa_ms'] * 1000);
        }
    }

    /** @return array<string, string> url => categoria del sitemap */
    private function urlsDeOfertas(array $config): array
    {
        $urls = [];
        foreach ($this->locs($this->get($config['sitemap'], $config)) as $sitemap) {
            $categoria = preg_match('#sitemap/([a-z-]+)-\d+\.xml#', $sitemap, $m) ? $m[1] : null;
            foreach ($this->locs($this->get($sitemap, $config)) as $url) {
                if (preg_match('/-\d{4,6}$/', $url)) {
                    $urls[$url] ??= $categoria;
                }
            }
        }

        return $urls;
    }

    private function normalizar(string $url, ?string $categoriaSitemap, string $html): array
    {
        $ld = $this->jsonLd($html);
        $producto = $ld['Product'] ?? [];
        $migas = array_column($ld['BreadcrumbList']['itemListElement'] ?? [], 'name');

        $precio = $this->monto($producto['offers']['price'] ?? null);
        $normal = preg_match('#<del class="cd2-product__old">([^<]+)</del>#', $html, $m) ? $this->monto($m[1]) : null;
        $vendidos = preg_match('/([\d,.]+)\s+vendidos/', $html, $m) ? (int) preg_replace('/\D/', '', $m[1]) : null;
        $descuento = ($precio && $normal && $normal > $precio) ? (int) round((1 - $precio / $normal) * 100) : null;

        return [
            'external_id' => preg_match('/-(\d{4,6})$/', $url, $m) ? $m[1] : $url,
            'titulo' => $this->texto($producto['name'] ?? null) ?? $url,
            'marca' => $this->texto($producto['brand']['name'] ?? null),
            'categoria' => $migas[0] ?? $categoriaSitemap,
            'subcategoria' => count($migas) > 2 ? $migas[1] : null,
            'tipo' => null,
            'url' => $url,
            'imagen' => $producto['image'] ?? null,
            'precio_oferta' => $precio,
            'precio_normal' => $normal,
            'descuento_pct' => $descuento,
            'vendidos' => $vendidos,
            'valoraciones' => null,
            'estrellas' => null,
            'stock_maximo' => null,
            'estado' => str_contains($producto['offers']['availability'] ?? '', 'InStock') ? 'disponible' : 'no_disponible',
            'fecha_termino' => null,
            'extra' => [
                'categoria_sitemap' => $categoriaSitemap,
                'dias_atencion' => preg_match('/class="cd2-offer__days" title="([^"]+)"/', $html, $m) ? html_entity_decode($m[1]) : null,
                'zonas' => preg_match('#cd2-product__place">.*?<span>(.*?)</span>#s', $html, $m) ? $this->texto($m[1]) : null,
                'vigencia' => preg_match('/Cup[oó]n v[aá]lido hasta[^<.]*/iu', $html, $m) ? $this->texto($m[0]) : null,
            ],
        ];
    }

    /** @return array<string, array> JSON-LD de la pagina indexado por @type */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $bloques);
        $porTipo = [];
        foreach ($bloques[1] as $bloque) {
            $json = json_decode($bloque, true);
            if (is_array($json) && isset($json['@type'])) {
                $porTipo[$json['@type']] = $json;
            }
        }

        return $porTipo;
    }

    private function get(string $url, array $config): string
    {
        return Http::withHeaders(['User-Agent' => $config['user_agent']])->timeout(40)->retry(3, 2000)->get($url)->throw()->body();
    }

    /** @return list<string> */
    private function locs(string $xml): array
    {
        preg_match_all('#<loc>([^<]+)</loc>#', $xml, $m);

        return $m[1];
    }

    private function texto(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $limpio = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($valor))));

        return $limpio === '' ? null : $limpio;
    }

    private function monto(mixed $valor): ?float
    {
        $limpio = preg_replace('/[^\d.]/', '', (string) $valor);

        return $limpio === '' ? null : (float) $limpio;
    }
}
