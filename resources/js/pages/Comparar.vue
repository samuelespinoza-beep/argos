<script setup>
// Cuponidad vs competidor, oferta por oferta: solo las que existen en los dos sitios,
// con el precio, lo vendido y el total de cada uno. Datos de /datos/cruce (DatosController).
import { computed, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import Pills from '../components/Pills.vue';
import { leerCruce, leerIndiceCruce } from '../lib/datos';
import { capitalizar, descargarCsv, dia as formatoDia, entero, fechaHora, sol } from '../lib/formato';

const props = defineProps({
    competidor: { type: String, default: '' }, // vacio = el primero del indice
    dia: { type: String, default: '' }, // vacio = la fecha mas reciente
});
const router = useRouter();

// ---- datos ----
const D = ref(null);
const fechas = ref([]);
const error = ref('');

async function cargar() {
    D.value = null;
    error.value = '';
    try {
        const indice = await leerIndiceCruce();
        const competidor = props.competidor || Object.keys(indice.competidores)[0];
        fechas.value = indice.competidores[competidor] ?? [];
        if (!fechas.value.length) {
            throw new Error(`No hay fechas con foto de Cuponidad y "${competidor}" a la vez. Corre "make scrape".`);
        }
        D.value = await leerCruce(competidor, props.dia || fechas.value[0]);
        document.title = `Cuponidad vs ${capitalizar(competidor)} – ${formatoDia(D.value.dia)}`;
    } catch (e) {
        error.value = e.message;
    }
}
watch(() => [props.competidor, props.dia], cargar, { immediate: true });

const cambiarDia = (d) => router.push(`/comparar/${D.value.competidor}/${d}`);
const nombreA = computed(() => capitalizar(D.value?.propio ?? ''));
const nombreB = computed(() => capitalizar(D.value?.competidor ?? ''));

// Cada par con atajos: a = nuestra oferta, b = la del competidor, ganaPrecio = quien es mas barato.
const pares = computed(() =>
    (D.value?.pares ?? []).map((p) => {
        const a = p[D.value.propio];
        const b = p[D.value.competidor];
        const dif = b.precio - a.precio;
        // Menos de 1% de diferencia (S/ 29.90 vs S/ 29.99) cuenta como mismo precio.
        const igual = Math.abs(dif) < Math.max(a.precio, b.precio) * 0.01;
        return { ...p, a, b, ganaPrecio: igual ? 'igual' : dif > 0 ? 'a' : 'b' };
    }),
);

// ---- filtros y orden ----
const DEF = { q: '', precio: '', confianza: '', categoria: '', cambio: '' };
const F = reactive({ ...DEF });
const orden = ref('a.vendidos');
const asc = ref(false);

const categorias = computed(() => [...new Set(pares.value.map((p) => p.categoria))].sort());
const OPC_CONFIANZA = [['', 'Todas'], ['alta', 'Alta'], ['media', 'Media (revisar)']];
const OPC_CAMBIO = [['', 'Todas'], ['vend', 'Vendieron'], ['precio', 'Cambió precio'], ['nueva', 'Nuevas'], ['retirada', 'Retiradas']];
const limpiar = () => Object.assign(F, DEF);

// "Cambios desde ayer" en un par: se cumple si pasa en cualquiera de los dos sitios.
const CAMBIOS = {
    vend: (o) => o.vendidos > 0,
    precio: (o) => o.cambio_precio != null,
    nueva: (o) => o.nueva,
    retirada: (o) => o.retirada,
};

// Valor de una columna a partir de su clave ("a.precio" -> par.a.precio).
const valor = (p, k) => k.split('.').reduce((v, parte) => v?.[parte], p);

// conPrecio = false ignora el filtro de precio (lo usan las tarjetas de precio para contar).
function pasa(p, conPrecio = true) {
    const q = F.q.toLowerCase();
    return (!q || `${p.marca} ${p.a.titulo} ${p.b.titulo}`.toLowerCase().includes(q))
        && (!conPrecio || !F.precio || p.ganaPrecio === F.precio)
        && (!F.confianza || p.confianza === F.confianza)
        && (!F.categoria || p.categoria === F.categoria)
        && (!F.cambio || CAMBIOS[F.cambio](p.a) || CAMBIOS[F.cambio](p.b));
}

const filtrados = computed(() => {
    const dir = asc.value ? 1 : -1;
    return pares.value
        .filter((p) => pasa(p))
        .sort((x, y) => {
            const vx = valor(x, orden.value);
            const vy = valor(y, orden.value);
            if ((vx == null) !== (vy == null)) return vx == null ? 1 : -1; // los vacios al final
            return (typeof vx === 'number' ? vx - vy : String(vx).localeCompare(String(vy))) * dir;
        });
});

function ordenarPor(k) {
    asc.value = orden.value === k ? !asc.value : k === 'a.titulo';
    orden.value = k;
}

// ---- KPIs (sobre lo filtrado) ----
const kpis = computed(() => {
    const r = filtrados.value;
    const suma = (k) => r.reduce((s, p) => s + (Number(valor(p, k)) || 0), 0);
    return {
        vendA: suma('a.vendidos'), vendB: suma('b.vendidos'),
        totalA: suma('a.total'), totalB: suma('b.total'),
    };
});

// ---- Tarjetas de precio: cuentan sin el filtro de precio (si no, al elegir una las otras marcarian 0) ----
const precios = computed(() => {
    const base = pares.value.filter((p) => pasa(p, false));
    const grupo = (g) => base.filter((p) => p.ganaPrecio === g);
    // Mayor diferencia en soles dentro del grupo ("hasta S/ 30 mas barato").
    const hasta = (lista) => Math.max(0, ...lista.map((p) => Math.abs(p.a.precio - p.b.precio)));
    return [
        { clave: 'a', n: grupo('a').length, texto: `${nombreA.value} más barata`, detalle: grupo('a').length ? `ganamos en precio · hasta ${sol(hasta(grupo('a')))} menos` : 'ganamos en precio', clase: 'cmp-k-verde' },
        { clave: 'igual', n: grupo('igual').length, texto: 'Mismo precio', detalle: 'sin diferencia (menos de 1%)', clase: 'cmp-k-gris' },
        { clave: 'b', n: grupo('b').length, texto: `${nombreB.value} más barata`, detalle: grupo('b').length ? `perdemos en precio · hasta ${sol(hasta(grupo('b')))} menos` : 'perdemos en precio', clase: 'cmp-k-rojo' },
    ];
});
// Clic en una tarjeta de precio: filtra por ese grupo; un segundo clic quita el filtro.
const alternarPrecio = (g) => (F.precio = F.precio === g ? '' : g);

const columnas = computed(() => [
    ['', ''], ['a.titulo', 'Oferta'],
    ['a.precio', `Precio ${nombreA.value}`], ['a.vendidos', `Vendidos ${nombreA.value}`], ['a.total', `Total ${nombreA.value}`],
    ['b.precio', `Precio ${nombreB.value}`], ['b.vendidos', `Vendidos ${nombreB.value}`], ['b.total', `Total ${nombreB.value}`],
]);

// Vendidos del periodo: "+N", "nueva" si no estaba en la foto anterior, "retirada" si ya no esta.
const vend = (o) => (o.retirada ? 'retirada' : o.nueva ? 'nueva' : o.vendidos == null ? '—' : `+${entero(o.vendidos)}`);

function exportar() {
    const A = D.value.propio;
    const B = D.value.competidor;
    const cols = ['marca', 'categoria', 'confianza', `titulo_${A}`, `precio_${A}`, `vendidos_${A}`, `total_${A}`, `url_${A}`,
        `titulo_${B}`, `precio_${B}`, `vendidos_${B}`, `total_${B}`, `url_${B}`];
    const filas = filtrados.value.map((p) => ({
        marca: p.marca, categoria: p.categoria, confianza: p.confianza,
        ...Object.fromEntries(['titulo', 'precio', 'vendidos', 'total', 'url'].flatMap((c) => [[`${c}_${A}`, p.a[c]], [`${c}_${B}`, p.b[c]]])),
    }));
    descargarCsv(`${A}-vs-${B}-${D.value.dia}-${filas.length}.csv`, cols, filas);
}
</script>

<template>
    <div v-if="error" class="aviso">{{ error }}</div>
    <div v-else-if="!D" class="cargando">Cargando comparación…</div>
    <template v-else>
        <div class="top">
            <div class="brand">
                {{ nombreA }} vs {{ nombreB }}
                <small>{{ fechaHora(D.fecha) }} · {{ pares.length }} ofertas en ambos sitios</small>
            </div>
            <select class="btn" :value="D.dia" title="Foto del día" @change="cambiarDia($event.target.value)">
                <option v-for="f in fechas" :key="f" :value="f">Foto del {{ formatoDia(f) }}</option>
            </select>
            <div class="search"><input v-model="F.q" type="search" placeholder="Buscar por marca u oferta…"></div>
            <button class="btn primary" @click="exportar">⤓ Exportar CSV</button>
        </div>

        <div class="cmp-pagina">
            <div class="kpis">
                <div class="kpi"><b>{{ entero(filtrados.length) }}</b><span>Ofertas en común</span></div>
                <div class="kpi"><b>+{{ entero(kpis.vendA) }} <small class="cmp-vs">vs +{{ entero(kpis.vendB) }}</small></b><span>Vendidos {{ D.desde }} ({{ nombreA }} vs {{ nombreB }})</span></div>
                <div class="kpi"><b>{{ sol(kpis.totalA) }} <small class="cmp-vs">vs {{ sol(kpis.totalB) }}</small></b><span>Total {{ D.desde }} ({{ nombreA }} vs {{ nombreB }})</span></div>
            </div>

            <!-- Quien tiene el mejor precio. Cada tarjeta filtra la tabla; un segundo clic quita el filtro. -->
            <div class="kpis cmp-precios">
                <button v-for="g in precios" :key="g.clave" class="kpi kpi-filtro" :class="[g.clase, { on: F.precio === g.clave }]" :title="`Ver solo: ${g.texto}`" @click="alternarPrecio(g.clave)">
                    <b>{{ g.n }}</b><span>{{ g.texto }}</span><small>{{ g.detalle }}</small>
                </button>
            </div>

            <div class="cmp-filtros">
                <div class="cmp-filtros-titulo">Filtros <button @click="limpiar">Limpiar todo</button></div>
                <div><label>Cambios {{ D.desde }} (en cualquiera de los dos sitios)</label><Pills v-model="F.cambio" :opciones="OPC_CAMBIO" /></div>
                <div><label>Coincidencia</label><Pills v-model="F.confianza" :opciones="OPC_CONFIANZA" /></div>
                <div>
                    <label>Categoría ({{ nombreA }})</label>
                    <select v-model="F.categoria"><option value="">Todas</option><option v-for="c in categorias" :key="c">{{ c }}</option></select>
                </div>
            </div>

            <p class="cmp">
                Solo aparecen las ofertas que están en los dos sitios: misma marca, título y precio parecidos.
                Coincidencia "media" = probablemente la misma, pero conviene revisarla con los links.
                Vendidos y total: {{ D.desde }} (foto anterior: {{ D.fecha_ant ? fechaHora(D.fecha_ant) : '—' }}).
            </p>

            <div class="tablewrap">
                <div v-if="!filtrados.length" class="empty">Ninguna oferta coincide con estos filtros.</div>
                <table v-else>
                    <thead>
                        <tr>
                            <th v-for="[k, etiqueta] in columnas" :key="k" :class="{ sort: k, centro: k && k !== 'a.titulo', 'cmp-grupo': k === 'a.precio' || k === 'b.precio', on: k && k === orden, asc: k === orden && asc }" @click="k && ordenarPor(k)">
                                {{ etiqueta }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in filtrados" :key="p.a.id">
                            <!-- Una oferta retirada no trae imagen: se usa la del otro sitio. -->
                            <td><img :src="p.a.imagen || p.b.imagen" loading="lazy" alt=""></td>
                            <td class="t">
                                <a :href="p.a.url" target="_blank">{{ p.a.titulo }}</a>
                                <small>
                                    {{ nombreB }}: <a :href="p.b.url" target="_blank" class="cmp-otro">{{ p.b.titulo }}</a>
                                </small>
                                <small>
                                    {{ p.marca }} · {{ p.categoria }}
                                    <span v-if="p.confianza === 'media'" class="cmp-media" title="Probablemente la misma oferta: revisar con los links">coincidencia media</span>
                                    <span v-if="p.a.retirada || p.b.retirada" class="cmp-retirada" :title="`Estaba en la foto anterior y ya no. Precio: el de la foto anterior.`">
                                        retirada de {{ p.a.retirada ? nombreA : nombreB }}
                                    </span>
                                </small>
                            </td>
                            <td class="n centro cmp-grupo"><span class="price" :class="{ 'cmp-gana': p.ganaPrecio === 'a' }">{{ sol(p.a.precio) }}</span></td>
                            <td class="n centro"><b :class="{ 'cmp-mas': (p.a.vendidos ?? 0) > (p.b.vendidos ?? 0) }">{{ vend(p.a) }}</b></td>
                            <td class="n centro">{{ p.a.total ? sol(p.a.total) : '—' }}</td>
                            <td class="n centro cmp-grupo"><span class="price" :class="{ 'cmp-gana': p.ganaPrecio === 'b' }">{{ sol(p.b.precio) }}</span></td>
                            <td class="n centro"><b :class="{ 'cmp-mas': (p.b.vendidos ?? 0) > (p.a.vendidos ?? 0) }">{{ vend(p.b) }}</b></td>
                            <td class="n centro">{{ p.b.total ? sol(p.b.total) : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </template>
</template>

<style scoped>
.cmp-pagina { max-width: 1600px; margin: 0 auto; padding: 20px; }
.cmp-vs { font-size: 13px; font-weight: 700; color: var(--muted); }
/* Tarjetas de precio: borde izquierdo de color segun si nos conviene o no. */
.cmp-precios .kpi { border-left-width: 4px; }
.cmp-precios .kpi > span { display: block; color: var(--fg); font-weight: 700; font-size: 13px; }
.cmp-precios .kpi small { display: block; color: var(--muted); font-size: 11.5px; margin-top: 2px; }
.cmp-k-verde { border-left-color: var(--ok); } .cmp-k-verde b { color: var(--ok); }
.cmp-k-gris { border-left-color: var(--muted); }
.cmp-k-rojo { border-left-color: var(--bad); } .cmp-k-rojo b { color: var(--bad); }
.cmp-filtros { display: flex; flex-wrap: wrap; gap: 14px 28px; align-items: flex-end; background: var(--card); border: 1px solid var(--line); border-radius: 8px; padding: 12px 16px; margin-bottom: 14px; }
.cmp-filtros-titulo { flex-basis: 100%; display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.cmp-filtros-titulo button { font-size: 12px; text-transform: none; letter-spacing: 0; background: none; border: 0; color: var(--accent); cursor: pointer; padding: 0; font-weight: 700; }
.cmp-filtros label { display: block; font-size: 12px; font-weight: 700; color: var(--muted); margin-bottom: 5px; }
.cmp-filtros select { padding: 6px 9px; border: 1px solid var(--line); border-radius: 5px; background: var(--card); min-width: 200px; }
.cmp { margin: 0 0 12px; }
.centro { text-align: center; }
/* Lineas que separan la columna Oferta, el bloque de Cuponidad y el de la competencia. */
.cmp-grupo { border-left: 2px solid var(--line); }
.cmp-otro { font-weight: 400 !important; }
.cmp-retirada { display: inline-block; margin-left: 4px; background: #dc26261f; color: var(--bad); border: 1px solid #dc262655; border-radius: 5px; padding: 0 5px; font-size: 10.5px; font-weight: 700; }
.cmp-media { display: inline-block; margin-left: 4px; background: #d977061f; color: var(--warn); border: 1px solid #d9770655; border-radius: 5px; padding: 0 5px; font-size: 10.5px; font-weight: 700; }
/* El precio mas barato del par en verde; el que vendio mas, en verde tambien. */
.price.cmp-gana { color: var(--ok); }
.cmp-mas { color: var(--ok); }
</style>
