<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Tablas de comparacion entre Cuponidad y un competidor, armadas con la foto
 * mas reciente de cada oferta activa (y el historial para las ventas). Cada
 * metodo devuelve filas listas para exportar o para leer en un reporte.
 */
class ComparacionCompetitiva
{
    public const PROPIO = 'cuponidad';

    /**
     * Foto mas reciente de cada oferta activa. marca_clave es la marca sin
     * tildes, mayusculas ni simbolos, para emparejar entre sitios.
     */
    private const ACTUAL = <<<'SQL'
        SELECT DISTINCT ON (o.id) o.*,
               regexp_replace(lower(translate(o.marca, 'áéíóúüñÁÉÍÓÚÜÑ', 'aeiouunaeiouun')), '[^a-z0-9]', '', 'g') AS marca_clave,
               s.fecha, s.precio_oferta, s.precio_normal, s.descuento_pct, s.vendidos,
               s.valoraciones, s.estrellas, s.estado
        FROM ofertas o JOIN oferta_snapshots s ON s.oferta_id = o.id
        WHERE o.activa AND o.sitio IN (:propio, :competidor)
        ORDER BY o.id, s.fecha DESC
        SQL;

    public function __construct(private string $competidor) {}

    /** Dias de historial y ofertas por sitio. */
    public function historial(): array
    {
        return $this->consultar(<<<'SQL'
            SELECT o.sitio, COUNT(DISTINCT s.fecha) AS dias, MIN(s.fecha) AS desde, MAX(s.fecha) AS hasta,
                   COUNT(DISTINCT o.id) FILTER (WHERE o.activa) AS ofertas_activas
            FROM oferta_snapshots s JOIN ofertas o ON o.id = s.oferta_id
            WHERE o.sitio IN (:propio, :competidor)
            GROUP BY o.sitio ORDER BY o.sitio
            SQL);
    }

    public function categorias(): array
    {
        return $this->consultar(<<<SQL
            WITH actual AS (%ACTUAL%)
            SELECT sitio, categoria, COUNT(*) AS ofertas,
                   percentile_cont(.5) WITHIN GROUP (ORDER BY precio_oferta) AS precio_mediano,
                   percentile_cont(.5) WITHIN GROUP (ORDER BY descuento_pct) AS dcto_mediano,
                   SUM(vendidos) AS vendidos_acumulados
            FROM actual GROUP BY sitio, categoria ORDER BY sitio, vendidos_acumulados DESC NULLS LAST
            SQL);
    }

    /** Marcas de ambos sitios: en comun, solo nosotros, solo ellos. */
    public function marcas(): array
    {
        return $this->consultar(<<<SQL
            WITH actual AS (%ACTUAL%),
            m AS (SELECT sitio, marca_clave, MIN(marca) AS marca, COUNT(*) AS ofertas, SUM(vendidos) AS vendidos,
                         MIN(precio_oferta) AS precio_min, string_agg(DISTINCT categoria, ', ') AS categorias
                  FROM actual WHERE marca_clave <> '' GROUP BY sitio, marca_clave),
            a AS (SELECT * FROM m WHERE sitio = :propio),
            b AS (SELECT * FROM m WHERE sitio = :competidor)
            SELECT CASE WHEN a.marca IS NULL THEN 'solo ellos' WHEN b.marca IS NULL THEN 'solo nosotros' ELSE 'ambos' END AS grupo,
                   COALESCE(a.marca, b.marca) AS marca,
                   a.ofertas AS nuestras_ofertas, a.vendidos AS nuestros_vendidos, a.precio_min AS nuestro_precio_min,
                   b.ofertas AS sus_ofertas, b.vendidos AS sus_vendidos, b.precio_min AS su_precio_min,
                   COALESCE(a.categorias, b.categorias) AS categorias
            FROM a FULL JOIN b USING (marca_clave)
            ORDER BY grupo, COALESCE(b.vendidos, 0) + COALESCE(a.vendidos, 0) DESC
            SQL);
    }

    /**
     * Marcas que no coinciden exacto pero una contiene a la otra (ej.
     * "Cineplanet Provincia 1" / "Cineplanet"). Hay que revisarlas a mano.
     */
    public function marcasParecidas(): array
    {
        return $this->consultar(<<<SQL
            WITH actual AS (%ACTUAL%),
            m AS (SELECT DISTINCT sitio, marca, marca_clave FROM actual WHERE marca_clave <> '')
            SELECT a.marca AS nuestra, b.marca AS suya
            FROM m a JOIN m b ON a.marca_clave <> b.marca_clave
             AND length(least(a.marca_clave, b.marca_clave)) >= 5
             AND (a.marca_clave LIKE '%' || b.marca_clave || '%' OR b.marca_clave LIKE '%' || a.marca_clave || '%')
            WHERE a.sitio = :propio AND b.sitio = :competidor
            ORDER BY a.marca
            SQL);
    }

    /** Cada oferta nuestra contra cada oferta suya de la misma marca. */
    public function precios(): array
    {
        return $this->consultar(<<<SQL
            WITH actual AS (%ACTUAL%)
            SELECT a.marca, a.titulo AS nuestra_oferta, a.precio_oferta AS nuestro_precio,
                   b.titulo AS su_oferta, b.precio_oferta AS su_precio,
                   a.precio_oferta - b.precio_oferta AS diferencia,
                   a.vendidos AS nuestros_vendidos, b.vendidos AS sus_vendidos, a.url AS nuestra_url, b.url AS su_url
            FROM actual a JOIN actual b ON a.marca_clave = b.marca_clave
            WHERE a.sitio = :propio AND b.sitio = :competidor AND a.marca_clave <> ''
            ORDER BY a.marca, a.titulo, b.precio_oferta
            SQL);
    }

    /** Ventas reales (diferencia de vendidos dia a dia) de los ultimos $dias dias. */
    public function ventas(int $dias): array
    {
        return $this->consultar(<<<'SQL'
            WITH d AS (
              SELECT s.oferta_id, s.precio_oferta,
                     s.vendidos - LAG(s.vendidos) OVER (PARTITION BY s.oferta_id ORDER BY s.fecha) AS delta
              FROM oferta_snapshots s WHERE s.fecha >= CURRENT_DATE - CAST(:dias AS int))
            SELECT o.sitio, o.marca, o.titulo, o.categoria, SUM(d.delta) AS ventas_periodo,
                   COUNT(d.delta) AS dias_medidos, MAX(d.precio_oferta) AS precio, o.url
            FROM d JOIN ofertas o ON o.id = d.oferta_id
            WHERE o.sitio IN (:propio, :competidor) AND d.delta IS NOT NULL AND d.delta >= 0
            GROUP BY o.id ORDER BY ventas_periodo DESC
            SQL, ['dias' => $dias]);
    }

    /** Todas las ofertas activas de ambos sitios, para filtrar en Excel. */
    public function ofertas(): array
    {
        return $this->consultar(<<<SQL
            WITH actual AS (%ACTUAL%)
            SELECT sitio, marca, titulo, categoria, subcategoria, precio_oferta, precio_normal, descuento_pct,
                   vendidos, valoraciones, estrellas, estado, primera_vez_vista, fecha AS foto_del, url
            FROM actual ORDER BY sitio, vendidos DESC NULLS LAST
            SQL);
    }

    private function consultar(string $sql, array $extra = []): array
    {
        $sql = str_replace('%ACTUAL%', self::ACTUAL, $sql);
        $filas = DB::select($sql, ['propio' => self::PROPIO, 'competidor' => $this->competidor, ...$extra]);

        return array_map(fn ($f) => (array) $f, $filas);
    }
}
