---
name: analisis-competitivo
description: Cómo comparar Cuponidad con un competidor usando la base de Argos (ofertas, oferta_snapshots, corridas) — consultas SQL listas, métricas, trampas de interpretación y el formato del reporte final con recomendaciones. Úsala para cualquier pregunta de "qué vende la competencia", "en qué somos más caros", "qué marcas nos faltan", "qué les funciona" o al redactar un reporte competitivo.
---

# Análisis competitivo

Siempre junto con la skill `contexto-cuponidad`. El resultado es un reporte para el equipo de Cuponidad: **qué tenemos, qué tiene el otro, qué le funciona y qué haríamos nosotros**.

## Datos
Postgres de Argos (contenedor `pgsql` de Sail). Desde la raíz de Argos:
```bash
docker compose exec -T pgsql psql -U sail -d laravel -c "SELECT ..."
# o una consulta larga:
docker compose exec -T pgsql psql -U sail -d laravel < storage/analisis/<carpeta>/consulta.sql
```
Si falla, el usuario debe levantar Argos: `./vendor/bin/sail up -d`.

Tablas:
- `ofertas` — ficha: `sitio`, `external_id`, `titulo`, `marca`, `categoria`, `subcategoria`, `url`, `activa`, `primera_vez_vista`, `ultima_vez_vista`.
- `oferta_snapshots` — foto diaria (una por oferta y `fecha`): `precio_oferta`, `precio_normal`, `descuento_pct`, `vendidos`, `valoraciones`, `estrellas`, `estado`, `extra` (jsonb).
- `corridas` — cada scraping: `sitio`, `estado`, `ofertas_encontradas/nuevas/retiradas`, `errores`.

Primero mira cuánto historial hay; eso decide qué análisis son posibles:
```sql
SELECT o.sitio, COUNT(DISTINCT s.fecha) AS dias, MIN(s.fecha), MAX(s.fecha), COUNT(DISTINCT o.id) AS ofertas
FROM oferta_snapshots s JOIN ofertas o ON o.id = s.oferta_id GROUP BY o.sitio;
```
- **1 día** → solo foto: catálogo, precios, marcas, vendidos acumulados.
- **≥ 2 días** → ventas reales (diferencias), ofertas nuevas/retiradas, cambios de precio.
- **≥ 7 días** → rankings semanales fiables.

## Consultas
Sustituye `'competidor'` por la clave del sitio. Las demás consultas usan la foto más reciente:

```sql
-- Foto más reciente de cada oferta activa (base de casi todo)
CREATE TEMP VIEW actual AS
SELECT DISTINCT ON (o.id) o.*,
       -- marca sin tildes, mayusculas ni simbolos, para emparejar entre sitios
       regexp_replace(lower(translate(o.marca, 'áéíóúüñÁÉÍÓÚÜÑ', 'aeiouunaeiouun')), '[^a-z0-9]', '', 'g') AS marca_clave,
       s.fecha, s.precio_oferta, s.precio_normal, s.descuento_pct,
       s.vendidos, s.valoraciones, s.estrellas, s.estado, s.extra
FROM ofertas o JOIN oferta_snapshots s ON s.oferta_id = o.id
WHERE o.activa ORDER BY o.id, s.fecha DESC;
```
(Las vistas TEMP viven solo en esa sesión de psql: ponlas en el mismo archivo `.sql` que las consultas.)

**1. Catálogo por categoría**
```sql
SELECT sitio, categoria, COUNT(*) ofertas,
       percentile_cont(.5) WITHIN GROUP (ORDER BY precio_oferta) precio_mediano,
       percentile_cont(.5) WITHIN GROUP (ORDER BY descuento_pct) dcto_mediano,
       SUM(vendidos) vendidos_acum
FROM actual WHERE sitio IN ('cuponidad','competidor') GROUP BY 1,2 ORDER BY 1,4 DESC;
```

**2. Marcas: comunes, solo ellos, solo nosotros** (el emparejado por nombre es aproximado: revisa los casos dudosos)
```sql
WITH m AS (SELECT sitio, marca_clave marca, COUNT(*) ofertas, SUM(vendidos) vendidos
           FROM actual WHERE sitio IN ('cuponidad','competidor') AND marca IS NOT NULL GROUP BY 1,2)
SELECT COALESCE(a.marca,b.marca) marca, a.ofertas nuestras, b.ofertas suyas,
       a.vendidos nuestros_vend, b.vendidos sus_vend,
       CASE WHEN a.marca IS NULL THEN 'solo ellos' WHEN b.marca IS NULL THEN 'solo nosotros' ELSE 'ambos' END grupo
FROM (SELECT * FROM m WHERE sitio='cuponidad') a
FULL JOIN (SELECT * FROM m WHERE sitio='competidor') b USING (marca)
ORDER BY grupo, COALESCE(b.vendidos,0) DESC;
```
"Solo ellos" con muchos vendidos = **oportunidad comercial** (comercios a captar).

Antes de declarar una marca "solo ellos", busca coincidencias parciales (una clave contiene a la otra, ej. "buffetmandarin" ⊂ "buffetinternacionalmandarin") y revísalas a mano:
```sql
SELECT DISTINCT a.marca nuestra, b.marca suya
FROM actual a JOIN actual b ON a.marca_clave <> b.marca_clave
 AND length(least(a.marca_clave, b.marca_clave)) >= 5
 AND (a.marca_clave LIKE '%'||b.marca_clave||'%' OR b.marca_clave LIKE '%'||a.marca_clave||'%')
WHERE a.sitio='cuponidad' AND b.sitio='competidor';
```
Referencia 26/10/2026 con Cuponatic: 63 marcas comunes exactas, ~100 sumando parciales revisadas.

**Antes de afirmar "solo ellos" o "nosotros no tenemos X", búscalo en Cuponidad por marca Y por título** (`marca ILIKE '%jump%' OR titulo ILIKE '%revisi%t%cnica%'`). El 26/10 el reporte dio por exclusivas de Cuponatic Jump Spot, Flying Squirrel, Safari Ranch y las revisiones técnicas, y Cuponidad las tenía con otro nombre (y vendía más en tres de ellas).

**3. Precios en marcas comunes**
```sql
SELECT a.marca, a.titulo nuestro, a.precio_oferta nuestro_precio,
       b.titulo suyo, b.precio_oferta su_precio, b.vendidos sus_vend
FROM actual a JOIN actual b ON a.marca_clave = b.marca_clave
WHERE a.sitio='cuponidad' AND b.sitio='competidor'
ORDER BY 1, a.precio_oferta;
```
Empareja a mano las ofertas equivalentes (mismo servicio/cantidad) antes de afirmar "somos más caros".

**4. Ventas reales por día** (necesita ≥ 2 días; cambia el 7 por el periodo)
```sql
WITH d AS (
  SELECT s.oferta_id, s.fecha, s.precio_oferta,
         s.vendidos - LAG(s.vendidos) OVER (PARTITION BY s.oferta_id ORDER BY s.fecha) AS delta
  FROM oferta_snapshots s WHERE s.fecha >= CURRENT_DATE - 7)
SELECT o.sitio, o.marca, o.titulo, o.categoria, SUM(d.delta) ventas_periodo,
       COUNT(d.delta) dias_medidos, MAX(d.precio_oferta) precio
FROM d JOIN ofertas o ON o.id = d.oferta_id
WHERE d.delta IS NOT NULL AND d.delta >= 0
GROUP BY 1,2,3,4 ORDER BY ventas_periodo DESC LIMIT 30;
```
Agrupa por `sitio, categoria` para ver qué categorías mueven cada sitio.

**5. Ofertas nuevas y retiradas**
```sql
SELECT sitio, primera_vez_vista, COUNT(*) nuevas FROM ofertas
WHERE primera_vez_vista >= CURRENT_DATE - 7
  AND primera_vez_vista > (SELECT MIN(fecha) FROM oferta_snapshots)  -- el primer día todas son "nuevas"
GROUP BY 1,2 ORDER BY 2 DESC;

SELECT sitio, marca, titulo, primera_vez_vista, ultima_vez_vista,
       ultima_vez_vista - primera_vez_vista AS dias_publicada
FROM ofertas WHERE NOT activa ORDER BY ultima_vez_vista DESC;
```

**6. Cambios de precio**
```sql
WITH p AS (SELECT oferta_id, fecha, precio_oferta,
                  LAG(precio_oferta) OVER (PARTITION BY oferta_id ORDER BY fecha) antes
           FROM oferta_snapshots)
SELECT o.sitio, o.marca, o.titulo, p.fecha, p.antes, p.precio_oferta ahora
FROM p JOIN ofertas o ON o.id = p.oferta_id
WHERE p.antes IS NOT NULL AND p.antes <> p.precio_oferta ORDER BY p.fecha DESC;
```

## Trampas (menciónalas en el reporte cuando apliquen)
- **"Vendidos" es acumulado y no auditado.** Comparar acumulados entre sitios compara antigüedad, no ventas. Para ventas usa la consulta 4. Una diferencia negativa = el sitio reinició el contador: se descarta.
- Si un día falta (corrida fallida, PC apagada), la diferencia siguiente acumula varios días: mira `dias_medidos`.
- Revisa `corridas`: una corrida con `estado='error'` o pocas ofertas distorsiona el día.
- Cobertura geográfica distinta (Cuponidad nacional; otros solo Lima).
- Estrellas o valoraciones decorativas/no confiables (Cuponidad: decorativas; Cuponatic "del comercio": siempre 3).
- El emparejado de marcas por nombre falla con variantes ("Buffet Mandarín" vs "Buffet Internacional Mandarín"): usa `marca_clave` + la consulta de parciales.
- **Cada sitio nombra distinto sus categorías** (Cuponidad "Restaurantes" = Cuponatic "gastronomia"; "Salud" ≈ "bienestar-y-salud"; Cuponidad con mayúscula inicial, Cuponatic en minúsculas con guiones). Para comparar por categoría, arma la equivalencia en la consulta con `CASE`.
- Cifras de un solo día = foto, no tendencia: dilo.

## Más allá de los números
Revisa también la **web** del competidor con el Chrome de Argos (skill `scraping-competidores`): qué promociones transversales tiene (códigos, cashback, relámpagos, gift cards), qué muestra en las tarjetas, menú y campañas de temporada, cómo capta comercios. Compara con las debilidades listadas en `contexto-cuponidad`.

## Entregables
Todo en `storage/analisis/<AAAAMMDD>-<sitio>/`:

1. **Excel y CSV**, siempre con el comando, sin armarlos a mano:
   ```bash
   ./vendor/bin/sail artisan argos:exportar <sitio> --dias=7
   ```
   Genera `cuponidad-vs-<sitio>.xlsx` (hojas: Historial, Categorias, Marcas, Marcas parecidas, Precios, Ventas N dias, Ofertas; con filtros) y `csv/` con una tabla por hoja (UTF-8 con BOM). Usa esos CSV como fuente de las cifras del reporte: así el HTML y el Excel coinciden.
   Las consultas de este documento son para preguntas puntuales que el export no cubre.

2. **`REPORTE.html`**: copia [plantilla-reporte.html](plantilla-reporte.html) y rellénala:
   - Reemplaza todo `{{...}}` y repite las filas de ejemplo; al terminar, `grep -c "{{" REPORTE.html` debe dar 0.
   - Mantén el CSS, el orden de secciones y el enlace al Excel. Sin JS ni recursos externos.
   - Barras: `style="--v:NN"` con NN = % respecto al máximo de esa tabla.
   - Clases: `mejor`/`peor` para resaltar precios o vendidos; `tag bajo|medio|alto` para el esfuerzo.
   - Secciones: En resumen (3–5 frases, lo más importante primero) · Cifras clave · Catálogo por categoría · Qué les está funcionando · Precios en marcas comunes · Lo que hacemos mejor · Recomendaciones (tabla #, qué hacer, por qué con dato, esfuerzo, área: Comercial / Precios / Web / Marketing) · Precauciones.
   - Ejemplo real terminado: `storage/analisis/20261026-cuponatic/REPORTE.html` (si existe).
   - Verifica que se vea bien: `google-chrome --headless=new --window-size=1200,2200 --screenshot=<png> file://<ruta>/REPORTE.html` y mira la imagen.

3. **Vista de ofertas** (cuando pidan "ver las ofertas" o "qué cambió"): `./vendor/bin/sail artisan argos:vista <sitio> [--fecha=AAAA-MM-DD] [--contra=AAAA-MM-DD]` genera `ofertas-<sitio>.html` con filtros, tarjetas/tabla y los cambios contra la foto anterior (vendidos, precio, nuevas, retiradas). Los datos van dentro del HTML; se puede compartir como archivo. La plantilla es `resources/argos/vista-ofertas.html`.

4. **Respuesta en el chat**: resumen corto (qué tenemos, qué tienen, qué les funciona, 3–5 recomendaciones) y las rutas del HTML y del Excel.

Busca patrones, no solo totales: misma oferta y mismo precio con ventas muy distintas (visibilidad/presentación), precio normal distinto para el mismo cupón (% de descuento aparente), la misma oferta publicada a dos precios, marcas "solo ellos" con muchos vendidos en nuestro rango de precio fuerte.

No inventes cifras: si un dato no está en la base, en el export o en la web, dilo.
