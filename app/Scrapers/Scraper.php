<?php

namespace App\Scrapers;

/**
 * Contrato de cada scraper de Argos. ofertas() devuelve las ofertas del sitio
 * normalizadas, con estas claves (las que el sitio no tenga van en null):
 *
 *   external_id, titulo, marca, categoria, subcategoria, tipo, url, imagen,
 *   precio_oferta, precio_normal, descuento_pct, vendidos, valoraciones,
 *   estrellas (0-5), stock_maximo, estado, fecha_termino (Y-m-d), extra (array)
 */
interface Scraper
{
    /** Clave del sitio, la misma que se guarda en ofertas.sitio. */
    public function sitio(): string;

    /** @return iterable<array<string, mixed>> */
    public function ofertas(): iterable;
}
