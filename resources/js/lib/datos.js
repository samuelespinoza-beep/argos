// Datos de la app: rutas /datos/... de routes/web.php (DatosController), que leen
// Postgres con cache en Redis. Ya no hay archivos JSON intermedios.

async function leer(url) {
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!r.ok) {
        // El backend responde { error: "..." } cuando no hay foto de esa fecha o sitio.
        const detalle = await r.json().catch(() => null);
        throw new Error(detalle?.error ?? `No se pudo leer ${url} (HTTP ${r.status}).`);
    }
    return r.json();
}

/** { actualizado, propio, sitios: { cuponatic: ['2026-10-28', ...] }, competidores: { cuponatic: [...] } } */
export const leerIndice = () => leer('/datos/indice');

/** Foto de un sitio en una fecha: { sitio, dia, dia_ant, fecha, fecha_ant, desde, ofertas[], retiradas[] } */
export const leerOfertas = (sitio, dia) => leer(`/datos/ofertas/${sitio}/${dia}`);

/** Competidores y fechas para "Ofertas en común" (vienen en el mismo indice). */
export const leerIndiceCruce = leerIndice;

/** Pares de ofertas que coinciden: { propio, competidor, dia, fecha, fecha_ant, desde, ofertas{}, pares[] } */
export const leerCruce = (competidor, dia) => leer(`/datos/cruce/${competidor}/${dia}`);
