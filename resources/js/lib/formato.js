export const vacio = (v) => v === '' || v == null;

export const sol = (v) =>
    vacio(v) ? '—' : 'S/ ' + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const entero = (v) => (vacio(v) ? '—' : Number(v).toLocaleString('es-PE'));

export const fechaHora = (iso) => new Date(iso).toLocaleString('es-PE', { dateStyle: 'medium', timeStyle: 'short' });

// "2026-10-27" -> "27/10/2026" (sin pasar por Date para no correr la zona horaria).
export const dia = (ymd) => ymd.split('-').reverse().join('/');

export const capitalizar = (s) => (s ? s[0].toUpperCase() + s.slice(1) : s);

/** Descarga filas como CSV (con BOM para que Excel respete las tildes). */
export function descargarCsv(nombre, columnas, filas) {
    const celda = (v) => {
        const s = String(v ?? '');
        return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
    };
    const csv = '﻿' + [columnas.join(','), ...filas.map((f) => columnas.map((c) => celda(f[c])).join(','))].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
    a.download = nombre;
    a.click();
    URL.revokeObjectURL(a.href);
}
