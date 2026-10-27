---
name: contexto-cuponidad
description: Contexto de negocio de Cuponidad (cuponidad.pe, sitio de cupones de Grupo La República) — qué vende, cómo vende, sus números, fortalezas, debilidades y competidores conocidos. Úsala siempre que haya que hablar de Cuponidad, compararla con otra página, proponer mejoras o promociones, o interpretar sus datos en Argos.
---

# Contexto de Cuponidad

Cuponidad es el sitio de cupones de descuento de **Grupo La República** (Perú). El usuario trabaja ahí. Todo análisis de Argos se hace **desde el punto de vista de Cuponidad**: la pregunta de fondo siempre es *"¿qué podemos hacer mejor o copiar?"*.

> Las cifras de abajo son del análisis inicial del **26/10/2026**. Para cifras actuales consulta la base de Argos (skill `analisis-competitivo`). Si difieren mucho de esto, dilo: es un cambio de negocio relevante.

## Qué es
- 7 categorías: Entretenimiento, Restaurantes, Belleza, Servicios, Viajes, Salud, Productos (+ campañas temporales, p. ej. "semana-santa").
- Alcance **nacional** (Lima y provincias). Cupón digital y/o impreso.
- Atención fuerte por **WhatsApp** (presente en casi todas las ofertas). Redes: Facebook, Instagram, TikTok (@cuponidadperu).
- Premio Ecommerce Award Perú 2019. El pie enlaza a larepublica.pe, libero.pe, wapa.pe, elpopular.pe, buenazo.pe (tráfico del grupo).

## Cómo vende (26/10/2026: 554 ofertas, 288 marcas)
| Categoría | Ofertas | % de vendidos | Precio mediano | Dcto. mediano |
|---|---|---|---|---|
| Entretenimiento | 80 | **79%** | S/ 32.9 | 38% |
| Restaurantes | 128 | 13% | S/ 44.9 | 31% |
| Belleza | 132 | 4% | S/ 59.5 | 75% |
| Servicios | 71 | 2% | S/ 99.0 | 42% |
| Viajes | 69 | 1% | S/ 135.0 | 34% |
| Salud | 49 | 1% | S/ 69.0 | 60% |
| Productos | 25 | 0.1% | S/ 59.9 | 54% |

- Ventas muy concentradas: **top 5% de ofertas = 85% de las ventas**.
- Subcategorías que más venden: Cines (Cineplanet sola ~207k vendidos), Karts, Juegos infantiles, Piscinas, Barra libre, Buffets, Makis/Sushi.
- **Precio S/ 0–60 = 91% de las ventas**; más de S/ 100 casi no vende.
- Más descuento no implica más ventas (por encima de ~30% da igual); sin descuento casi no vende.
- Belleza tiene muchas ofertas y descuentos altos pero vende poco: catálogo inflado.

## Fortalezas (no perderlas)
- Marcas ancla **exclusivas** en entretenimiento: Rally Kart, Mr. Joy, Fórmula Kart, Vacilandia, Perulandia, Go Enjoy City, Mercado Aviación.
- Días de atención y distrito visibles en cada tarjeta; WhatsApp en cada oferta.
- Alcance nacional (cines en provincias).
- Cifras de vendidos muy superiores a la competencia en las mismas marcas (Cineplanet 207k vs 12.5k en Cuponatic).
- Ojo: en otras marcas comunes pasa lo contrario con el mismo precio — La Bistecca (49 vs 5,045) y Euro Karts (919 vs 6,805) venden mucho más en Cuponatic. Problema de visibilidad/presentación, no de precio.

## Debilidades conocidas (vs. Cuponatic)
1. La tarjeta del listado **no muestra** precio normal, % de descuento ni vendidos (solo dentro de la oferta). Desperdicia su mejor prueba social.
2. **Sin valoraciones reales** (las 5 estrellas son decorativas).
3. Sin promociones transversales: código de descuento sitewide, cashback, ofertas relámpago, gift cards.
4. Sin "Cerca de mí" (distancia en km).
5. Sin pestañas temáticas/estacionales en el menú (Cyber, Verano, etc.).
6. Captación de comercios escondida en el pie ("Promociona tu negocio").

## Competidores conocidos
| Sitio | Estado en Argos | Notas |
|---|---|---|
| Cuponatic (cuponatic.com.pe) | Scraper diario | Solo Lima, ~1,100 ofertas, fuerte en Belleza (40% del catálogo). Tiene valoraciones de producto; las "del comercio" siempre valen 3 — no usarlas. Publica precio normal más alto (más % aparente) y a veces la misma oferta a dos precios. Reporte: `storage/analisis/20261026-cuponatic/` |

Al analizar un competidor nuevo, añádelo a esta tabla (pide confirmación al usuario antes de editar esta skill).

## Cómo interpretar los datos de Cuponidad
- **"Vendidos" es acumulado** desde que se publicó la oferta y no está auditado. Ventas reales = diferencia día a día (ver `analisis-competitivo`).
- Cuponidad incluye provincias; varios competidores son solo Lima: compara manzanas con manzanas.
- Cómo se scrapea: `app/Scrapers/CuponidadScraper.php` (sitemap → páginas de oferta, JSON-LD). No hay API.
