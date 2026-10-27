---
name: scraping-competidores
description: Cómo explorar una página de cupones/ofertas desconocida, encontrar de dónde salen sus datos (API, sitemap, HTML), extraer todas sus ofertas en el formato normalizado de Argos y cargarlas a la base; y cómo convertirla en un scraper diario. Úsala cuando haya que "analizar", "scrapear", "revisar" o "vigilar" una página nueva o arreglar un scraper de Argos.
---

# Scraping de competidores

Objetivo: obtener **todas** las ofertas de un sitio con el mismo formato que usan Cuponidad y Cuponatic en Argos, para poder compararlas con SQL.

## Herramientas
- **Chrome de Argos**: el usuario lo abre con `make chrome` (puerto 9226). Si no responde, pídele que lo abra; no lo abras tú.
- **`scripts/cdp.mjs`** (necesita Node 22+: usa `/home/linuxbrew/.linuxbrew/bin/node` o `nvm use 22`):
  ```bash
  node scripts/cdp.mjs tabs                  # pestañas
  node scripts/cdp.mjs open https://sitio.pe # abrir
  node scripts/cdp.mjs red sitio.pe 10       # recargar y listar APIs (XHR/fetch)
  node scripts/cdp.mjs eval sitio.pe "document.title"
  node scripts/cdp.mjs shot sitio.pe storage/analisis/x.png
  ```
- `curl` para APIs públicas y sitemaps.
- Carpeta de trabajo: `storage/analisis/<AAAAMMDD>-<sitio>/` (está en .gitignore). Ahí van scripts, JSON crudos y reportes.

## 1. Reconocimiento (en este orden, para por el primero que funcione)
1. **API JSON**: abre la home y una categoría, ejecuta `red` y busca respuestas `application/json` con listas de ofertas. Es lo mejor: rápido y completo (así es Cuponatic: `pe-api.cuponatic-latam.com/api2/cdn/descuentos/menu/<categoria>`). Prueba si acepta `limit`/`per_page` grandes.
2. **Datos embebidos en el HTML**: `__NEXT_DATA__`, `window.__NUXT__`, `window.__INITIAL_STATE__`, JSON-LD (`script[type="application/ld+json"]`).
3. **Sitemap**: `robots.txt` → `sitemap.xml` → URLs de ofertas; luego cada página (así es Cuponidad: JSON-LD `Product` + `<del>` con el precio normal + texto "N vendidos").
4. **HTML del listado** con `eval` (`querySelectorAll` sobre las tarjetas) y paginación/scroll.

Anota en el reporte qué método funcionó y los endpoints/selectores exactos: es lo que luego hace falta para el scraper diario.

## 2. Reglas de buena conducta (obligatorias)
- Respeta `robots.txt`: no toques buscador, carrito, checkout ni área de cliente.
- Máximo **3 peticiones en paralelo** y pausa ≥ 0.5 s entre lotes; con APIs, ≥ 1 s entre llamadas.
- Nada de login, compras, formularios ni descargas de archivos pesados.
- No guardes datos personales (nombres/teléfonos/correos de personas). Datos de comercios sí.
- Si el sitio bloquea (403/429/captcha), detente y avisa; no intentes evadirlo.

## 3. Formato normalizado
Un JSON con una **lista** de ofertas. Claves (las que el sitio no tenga, `null`):

| Clave | Tipo | Notas |
|---|---|---|
| `external_id` | string | **Obligatorio.** ID estable del sitio (de la URL o la API) |
| `titulo` | string | **Obligatorio** |
| `marca` | string | Nombre del comercio, tal cual lo muestra el sitio |
| `categoria`, `subcategoria` | string | Las del sitio, en minúsculas |
| `tipo` | string | servicio / producto / viaje, si el sitio lo distingue |
| `url` | string | **Obligatorio.** Absoluta |
| `imagen` | string | Absoluta |
| `precio_oferta`, `precio_normal` | número | Soles, sin "S/" ni comas |
| `descuento_pct` | entero | Si no viene, calcúlalo: `round((1 - oferta/normal) * 100)` |
| `vendidos` | entero | Contador público (acumulado) |
| `valoraciones` | entero | Nº de opiniones |
| `estrellas` | número 0–5 | Convierte escalas distintas (ej. /10 → /2). Si son decorativas, `null` |
| `stock_maximo` | entero | |
| `estado` | string | ej. activa / agotada |
| `fecha_termino` | `AAAA-MM-DD` | |
| `extra` | objeto | Todo lo demás útil: zonas, distritos, días de atención, etiquetas, cuotas, envío… |

Escribe la extracción como script reproducible (`storage/analisis/<carpeta>/scrape.mjs` o `.php`) que genere `ofertas.json`. Verifica antes de cargar: cuenta de ofertas vs. lo que muestra el sitio, 3–5 ofertas revisadas a mano en el navegador, precios en soles con sentido.

## 4. Cargar a Argos
```bash
./vendor/bin/sail artisan argos:importar <sitio> storage/analisis/<carpeta>/ofertas.json
```
`<sitio>` = clave corta en minúsculas (ej. `ofertasimple`). Crea/actualiza las ofertas y la foto del día igual que el scraping diario. Si lo corres dos veces el mismo día, la foto se actualiza (no duplica).

## 5. Pasar a seguimiento diario (solo si el usuario lo pide)
1. Crear `app/Scrapers/<Sitio>Scraper.php` implementando `App\Scrapers\Scraper`, con la misma lógica del script (copia el estilo de `CuponaticScraper` si es API o `CuponidadScraper` si es sitemap/HTML; usa `Http` de Laravel con `timeout`, `retry` y pausas).
2. Añadir el sitio a `config/argos.php` → `sitios` (URLs, pausas, categorías: nada de secretos; si hiciera falta un token, va en `.env`).
3. Probar: `./vendor/bin/sail artisan argos:scrape <sitio>` → debe dar `ok`, 0 errores y el mismo número de ofertas que el análisis puntual.
4. Desde entonces corre solo a las 06:00 (Lima) con `argos:scrape`.

Confirma con el usuario antes de escribir el scraper; él hace los commits.
