# Argos

Argos vigila a Cuponidad (cuponidad.pe) y a su competencia. Todos los días a las 06:00 (hora de Lima) toma una foto de cada oferta de cada sitio (precio, descuento y vendidos) y la compara con la foto anterior para mostrar:

- Cuánto vendió cada oferta desde la foto anterior y cuánto ingreso representa.
- Qué ofertas son nuevas, cuáles se retiraron y cuáles cambiaron de precio.
- Las ofertas que Cuponidad y un competidor tienen en común, con sus precios y ventas lado a lado.

Sitios configurados: **Cuponidad** (sitio propio) y **Cuponatic** (competencia).

Stack: Laravel 13, Vue 3 con Inertia, Postgres y Redis, todo levantado con Laravel Sail.

## Puesta en marcha

Requiere Docker. El `Makefile` envuelve los comandos de Sail (`make help` los lista todos).

```sh
cp .env.example .env    # luego ajustar: DB_CONNECTION=pgsql, DB_HOST=pgsql, REDIS_HOST=redis, APP_PORT=8070
composer install        # o: docker run --rm -v "$PWD":/app -w /app composer install
make dev                # app, Postgres, Redis y el scheduler
./vendor/bin/sail artisan key:generate
make migrate
./vendor/bin/sail npm install
make build
```

| Servicio | Dirección |
|---|---|
| Panel | http://localhost:8070 |
| Postgres (desde el host) | localhost:5433 |
| Redis (desde el host) | localhost:6382 |

Las credenciales van solo en `.env`, que no se versiona.

## Uso diario

El contenedor `scheduler` corre `argos:scrape` todos los días a las 06:00 (Lima). Solo funciona mientras los contenedores estén arriba: con `make down` no hay foto ese día. Las ventas de un día son, por eso, lo vendido entre las 06:00 del día anterior y las 06:00 de ese día.

| Comando | Qué hace |
|---|---|
| `make scrape` / `make scrape SITIO=cuponatic` | Toma la foto del día ahora, de todos los sitios o de uno |
| `make vista SITIO=cuponidad` | HTML con filtros de las ofertas y sus cambios (`argos:vista`, opciones `--fecha` y `--contra`) |
| `make exportar SITIO=cuponatic` | Excel y CSV de Cuponidad contra un competidor (`argos:exportar`, opción `--dias`) |
| `sail artisan argos:importar <sitio> <archivo.json>` | Carga un JSON de ofertas normalizadas como foto del día de un sitio (análisis puntuales) |
| `make front` | Vite en modo desarrollo |
| `make build` | Compila el front; necesario si no está corriendo `make front` |
| `make clear` | Limpia config, rutas, vistas y caché |
| `make test` | Corre los tests |

Los HTML, Excel y CSV que generan los comandos quedan en `storage/analisis/<fecha>-<sitio>/`. Esa carpeta no se versiona.

## Panel

| Ruta | Página |
|---|---|
| `/` | Inicio: cada sitio con su última foto y cuántas fotos tiene |
| `/ofertas/{sitio}?dia=AAAA-MM-DD` | Ofertas de un sitio y sus cambios respecto a la foto anterior |
| `/comparar/{competidor?}?dia=AAAA-MM-DD` | Ofertas en común entre Cuponidad y un competidor |

`PanelController` elige la página y le pasa los datos como props de Inertia. Los datos que arma `DatosArgos` se guardan en la caché de Redis, así que después de cambiar la lógica de cálculo hay que correr `make clear` para ver el cambio.

## Estructura

| Carpeta / archivo | Contenido |
|---|---|
| `config/argos.php` | Sitios, hora diaria y parámetros de cada scraper |
| `app/Scrapers/` | Un scraper por sitio: Cuponidad lee su sitemap y cada página de oferta; Cuponatic usa su API pública por categoría. `ArchivoScraper` lee un JSON (para `argos:importar`) |
| `app/Services/RegistrarOfertas.php` | Guarda las ofertas (`ofertas`) y su foto del día (`oferta_snapshots`) dentro de una `corrida` |
| `app/Services/FotoOfertas.php` | Arma la foto de un sitio y su diferencia con la foto anterior |
| `app/Services/ComparacionCompetitiva.php`, `CruceOfertas.php` | Cruce de ofertas entre sitios y ventas por período |
| `app/Services/DatosArgos.php` | Datos del panel, con caché |
| `resources/js/pages/` | Páginas Vue: `Inicio`, `Ofertas`, `Comparar` |
| `resources/argos/vista-ofertas.html` | Plantilla del HTML de `argos:vista` |

Tablas: `corridas` (cada ejecución del scraping), `ofertas` (una fila por oferta y sitio) y `oferta_snapshots` (la foto diaria de cada oferta).

## Decisiones a tener en cuenta

- **Los vendidos de Cuponidad se dividen entre 2** (`factor_vendidos => 0.5` en `config/argos.php`). El contador público de cuponidad.pe cuenta cada cupón dos veces; se confirmó contra la base de Cuponidad el 02/11/2026 (518 en el contador = 259 cupones). En la base de Argos se guarda el contador tal como se publica y el factor se aplica al leer, así que se puede revertir sin tocar datos. Si Cuponidad corrige su contador, el factor tendría que aplicarse solo a las fechas anteriores a la corrección.
- **El panel no envía Referer** (`<meta name="referrer" content="no-referrer">` en `app.blade.php`). Abrir una oferta de cuponidad.pe desde el panel dejaba cookies con `http://localhost`, y el WAF de CloudFront de cuponidad.pe respondía 403 a todas las visitas siguientes.
- **Los cambios se hacen solo del lado de Argos.** Argos no tiene acceso a la base de datos de Cuponidad.
