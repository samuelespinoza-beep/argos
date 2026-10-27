/**
 * cdp.mjs
 * -----------------------------------------------------------------------------
 * Control minimo del Chrome de depuracion de Argos (make chrome, puerto 9226)
 * por Chrome DevTools Protocol, para que Claude pueda mirar paginas sin MCP.
 *
 * Necesita Node 22+ (WebSocket nativo).
 *
 * USO:
 *   node scripts/cdp.mjs tabs                       # pestañas abiertas
 *   node scripts/cdp.mjs open <url>                 # abre una pestaña nueva
 *   node scripts/cdp.mjs eval <tab> "<js>"          # ejecuta JS y muestra el resultado
 *   node scripts/cdp.mjs shot <tab> <archivo.png>   # captura de pantalla
 *   node scripts/cdp.mjs red <tab> [segundos]       # recarga y lista las
 *                                                   # peticiones XHR/fetch (APIs)
 *
 * <tab> es el numero que muestra "tabs" o un texto contenido en la URL.
 * Variable CDP_PORT para usar otro puerto (por defecto 9226).
 * -----------------------------------------------------------------------------
 */

import fs from 'node:fs';

const PORT = process.env.CDP_PORT || 9226;
const BASE = `http://localhost:${PORT}`;
const [cmd, ...args] = process.argv.slice(2);

async function pestañas() {
    const res = await fetch(`${BASE}/json/list`).catch(() => null);
    if (!res) {
        console.error(`No hay Chrome en el puerto ${PORT}. Ejecuta: make chrome`);
        process.exit(1);
    }
    return (await res.json()).filter((t) => t.type === 'page');
}

async function buscar(tab) {
    const lista = await pestañas();
    const t = /^\d+$/.test(tab) ? lista[Number(tab)] : lista.find((p) => p.url.includes(tab));
    if (!t) {
        console.error(`No encontre la pestaña "${tab}"`);
        process.exit(1);
    }
    return t;
}

function conectar(t) {
    const ws = new WebSocket(t.webSocketDebuggerUrl);
    let id = 0;
    const pendientes = new Map();
    const eventos = [];
    ws.onmessage = (m) => {
        const msg = JSON.parse(m.data);
        if (msg.id && pendientes.has(msg.id)) {
            pendientes.get(msg.id)(msg);
            pendientes.delete(msg.id);
        } else if (msg.method) {
            eventos.push(msg);
        }
    };
    const enviar = (method, params = {}) =>
        new Promise((resolve) => {
            const n = ++id;
            pendientes.set(n, resolve);
            ws.send(JSON.stringify({ id: n, method, params }));
        });
    return new Promise((resolve) => (ws.onopen = () => resolve({ ws, enviar, eventos })));
}

if (cmd === 'tabs') {
    (await pestañas()).forEach((t, i) => console.log(`${i}\t${t.title.slice(0, 60)}\t${t.url}`));
} else if (cmd === 'open') {
    const res = await fetch(`${BASE}/json/new?${encodeURI(args[0])}`, { method: 'PUT' });
    const t = await res.json();
    console.log(`Abierta: ${t.url}`);
} else if (cmd === 'eval') {
    const { ws, enviar } = await conectar(await buscar(args[0]));
    const r = await enviar('Runtime.evaluate', { expression: args[1], returnByValue: true, awaitPromise: true });
    const v = r.result?.result?.value ?? r.result?.exceptionDetails ?? r.result;
    console.log(typeof v === 'string' ? v : JSON.stringify(v, null, 2));
    ws.close();
} else if (cmd === 'shot') {
    const { ws, enviar } = await conectar(await buscar(args[0]));
    const r = await enviar('Page.captureScreenshot', { format: 'png' });
    fs.writeFileSync(args[1], Buffer.from(r.result.data, 'base64'));
    console.log(`Guardada: ${args[1]}`);
    ws.close();
} else if (cmd === 'red') {
    const { ws, enviar, eventos } = await conectar(await buscar(args[0]));
    await enviar('Network.enable');
    await enviar('Page.reload');
    await new Promise((r) => setTimeout(r, Number(args[1] || 8) * 1000));
    const vistas = new Set();
    for (const e of eventos) {
        if (e.method !== 'Network.responseReceived') continue;
        const { type, response } = e.params;
        if (!['XHR', 'Fetch'].includes(type) || vistas.has(response.url)) continue;
        vistas.add(response.url);
        console.log(`${response.status}\t${response.mimeType}\t${response.url}`);
    }
    ws.close();
} else {
    console.log('Comandos: tabs | open <url> | eval <tab> "<js>" | shot <tab> <png> | red <tab> [seg]');
}
