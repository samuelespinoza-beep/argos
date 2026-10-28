<?php

namespace App\Scrapers;

/**
 * Lee ofertas ya normalizadas desde un archivo JSON (una lista con las claves
 * de la interfaz Scraper). Sirve para cargar a la base el analisis puntual de
 * una pagina nueva, sin escribirle todavia un scraper propio.
 */
class ArchivoScraper implements Scraper
{
    private const CLAVES = [
        'external_id', 'titulo', 'marca', 'categoria', 'subcategoria', 'tipo', 'url', 'imagen',
        'precio_oferta', 'precio_normal', 'descuento_pct', 'vendidos', 'valoraciones',
        'estrellas', 'stock_maximo', 'estado', 'fecha_termino', 'extra',
    ];

    public function __construct(private string $sitio, private string $archivo) {}

    public function sitio(): string
    {
        return $this->sitio;
    }

    public function ofertas(): iterable
    {
        $lista = json_decode(file_get_contents($this->archivo), true, flags: JSON_THROW_ON_ERROR);

        if (!array_is_list($lista)) {
            throw new \RuntimeException("{$this->archivo}: se esperaba una lista JSON de ofertas");
        }

        foreach ($lista as $d) {
            if (empty($d['external_id']) || empty($d['titulo']) || empty($d['url'])) {
                throw new \RuntimeException("{$this->archivo}: cada oferta necesita external_id, titulo y url");
            }

            $oferta = array_fill_keys(self::CLAVES, null);
            foreach (self::CLAVES as $clave) {
                $oferta[$clave] = $d[$clave] ?? null;
            }
            $oferta['external_id'] = (string) $d['external_id'];

            yield $oferta;
        }
    }
}
