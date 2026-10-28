<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import OfertasTabla from '../components/OfertasTabla.vue';
import Pills from '../components/Pills.vue';
import { capitalizar, descargarCsv, dia as formatoDia, entero, fechaHora, sol, vacio } from '../lib/formato';

const props = defineProps({
    sitio: { type: String, required: true },
    fechas: { type: Array, required: true },
    foto: { type: Object, default: null }, // { sitio, dia, dia_ant, fecha, fecha_ant, desde, ofertas[], retiradas[] }; null = sin fotos
});

const D = computed(() => props.foto);
const error = computed(() => (props.foto ? '' : `No hay fotos de "${props.sitio}" en la base. Corre "make scrape".`));

const cambiarDia = (d) => router.get(`/ofertas/${props.sitio}/${d}`, {}, { preserveState: true });

const O = computed(() =>
    (D.value?.ofertas ?? []).map((o) => ({ ...o, _cats: o.categoria.split(' | ') })),
);
const desde = computed(() => D.value?.desde ?? '');
const comparada = computed(() => !!D.value?.fecha_ant);

const DEF = { q: '', cats: [], sub: '', marca: '', tipo: '', cambio: '' };
const F = reactive(structuredClone(DEF));
const orden = ref('delta_vend');
const asc = ref(false);
const limite = ref(60);
const filtrosAbiertos = ref(false);

const limpiar = () => Object.assign(F, structuredClone(DEF));
watch([F, orden, asc], () => (limite.value = 60));

const categorias = computed(() =>
    [...new Set(O.value.flatMap((o) => o._cats))].sort().map((c) => ({ c, n: O.value.filter((o) => o._cats.includes(c)).length })),
);
const subcategorias = computed(() =>
    [...new Set(O.value.filter((o) => !F.cats.length || o._cats.some((c) => F.cats.includes(c))).map((o) => o.subcategoria).filter(Boolean))].sort(),
);
// La subcategoria solo aporta si alguna categoria se divide en 2 o mas (Cuponidad: Viajes -> Full Days, Hoteles...).
// En Cuponatic repite el nombre de la categoria, asi que ahi el filtro se oculta.
const subcategoriaAporta = computed(() => {
    const porCategoria = {};
    for (const o of O.value) {
        if (o.subcategoria) (porCategoria[o._cats[0]] ??= new Set()).add(o.subcategoria);
    }
    return Object.values(porCategoria).some((subs) => subs.size > 1);
});
watch(subcategorias, (subs) => {
    if (!subs.includes(F.sub)) F.sub = '';
});
const marcas = computed(() => [...new Set(O.value.map((o) => o.marca).filter(Boolean))].sort());
const tipos = computed(() => [['', 'Todos'], ...[...new Set(O.value.map((o) => o.tipo).filter(Boolean))].sort().map((t) => [t, capitalizar(t)])]);
const OPC = {
    cambio: [['', 'Todas'], ['vend', 'Vendieron'], ['precio', 'Cambió precio'], ['nueva', 'Nuevas'], ['retirada', 'Retiradas']],
};
const ORDENES = computed(() => [
    ['delta_vend:desc', `Más vendidos ${desde.value}`], ['ingreso:desc', `Más facturado ${desde.value}`], ['vendidos:desc', 'Más vendidos'],
    ['descuento_pct:desc', 'Mayor descuento'], ['precio_oferta:asc', 'Precio: menor a mayor'], ['precio_oferta:desc', 'Precio: mayor a menor'],
    ['ahorro:desc', 'Mayor ahorro (S/)'], ['valoraciones:desc', 'Más valoraciones'], ['estrellas_5:desc', 'Mejor calificadas'],
    ['fecha_termino:asc', 'Vencen antes'], ['titulo:asc', 'Título A–Z'],
]);
const ordenSelect = computed({
    get: () => `${orden.value}:${asc.value ? 'asc' : 'desc'}`,
    set: (v) => {
        const [k, d] = v.split(':');
        orden.value = k;
        asc.value = d === 'asc';
    },
});
function ordenarPor(k) {
    asc.value = orden.value === k ? !asc.value : k === 'titulo' || k === 'fecha_termino';
    orden.value = k;
}

// conCambio = false ignora el filtro "Cambios" (lo usan los contadores de las tarjetas-filtro).
function pasa(o, conCambio = true) {
    const q = F.q.toLowerCase();
    if (q && !`${o.titulo} ${o.marca} ${o.id}`.toLowerCase().includes(q)) return false;
    if (F.cats.length && !o._cats.some((c) => F.cats.includes(c))) return false;
    if (F.sub && o.subcategoria !== F.sub) return false;
    if (F.marca && !o.marca.toLowerCase().includes(F.marca.toLowerCase())) return false;
    if (F.tipo && o.tipo !== F.tipo) return false;
    if (!conCambio) return true;
    if (F.cambio === 'vend' && !(o.delta_vend > 0)) return false;
    if (F.cambio === 'precio' && vacio(o.delta_precio)) return false;
    if (F.cambio === 'nueva' && !o.nueva) return false;
    if (F.cambio === 'retirada') return false; // las retiradas no estan en la foto de hoy: van en su propia tabla
    return true;
}

const verRetiradas = computed(() => F.cambio === 'retirada');
const retiradas = computed(() => {
    const q = F.q.toLowerCase();
    return (D.value?.retiradas ?? []).filter((o) => (!q || `${o.titulo} ${o.marca} ${o.id}`.toLowerCase().includes(q))
        && (!F.cats.length || F.cats.includes(o.categoria))
        && (!F.marca || (o.marca ?? '').toLowerCase().includes(F.marca.toLowerCase())));
});
const filtradas = computed(() => {
    const k = orden.value;
    const dir = asc.value ? 1 : -1;
    return O.value.filter((o) => pasa(o)).sort((a, b) => {
        const x = a[k];
        const y = b[k];
        if (vacio(x) !== vacio(y)) return vacio(x) ? 1 : -1;
        return (typeof x === 'number' ? x - y : String(x).localeCompare(String(y))) * dir;
    });
});
const visibles = computed(() => filtradas.value.slice(0, limite.value));

const kpis = computed(() => {
    const r = filtradas.value;
    const suma = (k) => r.reduce((s, o) => s + (Number(o[k]) || 0), 0);
    // Las tarjetas-filtro cuentan sin el filtro "Cambios": si no, al activar "Nuevas" la otra marcaria 0.
    const base = O.value.filter((o) => pasa(o, false));
    return {
        deltaVend: suma('delta_vend'),
        ingreso: suma('ingreso'),
        cambioPrecio: base.filter((o) => !vacio(o.delta_precio)).length,
        nuevas: base.filter((o) => o.nueva).length,
    };
});
const alternarCambio = (valor) => (F.cambio = F.cambio === valor ? '' : valor);

const chips = computed(() => {
    const c = [];
    if (F.q) c.push(['q', `"${F.q}"`]);
    F.cats.forEach((x) => c.push([`cats:${x}`, x.replace(/-/g, ' ')]));
    if (F.sub) c.push(['sub', F.sub]);
    if (F.marca) c.push(['marca', `Marca: ${F.marca}`]);
    if (F.tipo) c.push(['tipo', F.tipo]);
    if (F.cambio) c.push(['cambio', { vend: `Vendieron ${desde.value}`, precio: 'Cambió precio', nueva: 'Nuevas', retirada: `Retiradas ${desde.value}` }[F.cambio]]);
    return c;
});
function quitarChip(k) {
    if (k.startsWith('cats:')) F.cats = F.cats.filter((c) => c !== k.slice(5));
    else F[k] = DEF[k];
}

function exportar() {
    if (verRetiradas.value) {
        const cols = ['id', 'titulo', 'marca', 'categoria', 'precio_oferta', 'vendidos', 'url'];
        return descargarCsv(`${D.value.sitio}-${D.value.dia}-retiradas-${retiradas.value.length}.csv`, cols, retiradas.value);
    }
    const cols = ['id', 'titulo', 'marca', 'categoria', 'subcategoria', 'precio_oferta', 'precio_ant', 'delta_precio', 'precio_normal', 'descuento_pct', 'ahorro',
        'estrellas_5', 'valoraciones', 'valoraciones_de', 'vendidos', 'vendidos_ant', 'delta_vend', 'ingreso', 'nueva', 'stock_maximo', 'estado', 'fecha_termino', 'tipo', 'url'];
    descargarCsv(`${D.value.sitio}-${D.value.dia}-filtrado-${filtradas.value.length}.csv`, cols, filtradas.value);
}
</script>

<template>
    <Head :title="D ? `${capitalizar(sitio)} – Ofertas ${formatoDia(D.dia)}` : capitalizar(sitio)" />
    <div v-if="error" class="aviso">{{ error }}</div>
    <template v-else>
        <div class="top">
            <div class="brand">
                {{ capitalizar(D.sitio) }}
                <small>{{ fechaHora(D.fecha) }} · {{ O.length }} ofertas</small>
            </div>
            <select class="btn" :value="D.dia" title="Foto del día" @change="cambiarDia($event.target.value)">
                <option v-for="f in fechas" :key="f" :value="f">Foto del {{ formatoDia(f) }}</option>
            </select>
            <div class="search"><input v-model="F.q" type="search" placeholder="Buscar por título, marca o ID…"></div>
            <button class="btn filters-toggle" @click="filtrosAbiertos = !filtrosAbiertos">Filtros</button>
            <select v-model="ordenSelect" class="btn">
                <option v-for="[v, etiqueta] in ORDENES" :key="v" :value="v">{{ etiqueta }}</option>
            </select>
            <button class="btn primary" @click="exportar">⤓ Exportar CSV</button>
        </div>

        <div class="layout">
            <aside :class="{ open: filtrosAbiertos }">
                <h2>Filtros <button @click="limpiar">Limpiar todo</button></h2>
                <div class="f"><label class="t">Cambios {{ desde }}</label><Pills v-model="F.cambio" :opciones="OPC.cambio" /></div>
                <div class="f">
                    <label class="t">Categoría</label>
                    <div class="checks">
                        <label v-for="{ c, n } in categorias" :key="c">
                            <input v-model="F.cats" type="checkbox" :value="c"> {{ c.replace(/-/g, ' ') }}<span class="c">{{ n }}</span>
                        </label>
                    </div>
                </div>
                <div v-if="subcategoriaAporta" class="f">
                    <label class="t">Subcategoría</label>
                    <select v-model="F.sub"><option value="">Todas</option><option v-for="s in subcategorias" :key="s">{{ s }}</option></select>
                </div>
                <div class="f">
                    <label class="t">Marca</label>
                    <input v-model="F.marca" type="text" list="marcas" placeholder="Escribe una marca…">
                    <datalist id="marcas"><option v-for="m in marcas" :key="m" :value="m" /></datalist>
                </div>
                <div v-if="tipos.length > 1" class="f"><label class="t">Tipo</label><Pills v-model="F.tipo" :opciones="tipos" /></div>
            </aside>

            <main>
                <div class="kpis">
                    <div class="kpi"><b class="up">+{{ entero(kpis.deltaVend) }}</b><span>Vendidos {{ desde }}</span></div>
                    <div class="kpi"><b class="up">{{ sol(kpis.ingreso) }}</b><span>Ventas estimadas {{ desde }}</span></div>
                    <button class="kpi kpi-filtro" :class="{ on: F.cambio === 'precio' }" title="Ver solo las que cambiaron precio" @click="alternarCambio('precio')">
                        <b>{{ entero(kpis.cambioPrecio) }}</b><span>Cambiaron precio</span>
                    </button>
                    <button class="kpi kpi-filtro" :class="{ on: F.cambio === 'nueva' }" title="Ver solo las nuevas" @click="alternarCambio('nueva')">
                        <b>{{ entero(kpis.nuevas) }}</b><span>Nuevas</span>
                    </button>
                </div>

                <div class="cmp">
                    <template v-if="comparada">
                        Comparado con la foto del {{ fechaHora(D.fecha_ant) }} · {{ D.retiradas.length }} ofertas retiradas desde entonces (al final de la página).<br>
                        Ventas estimadas = vendidos en el periodo × precio de la oferta. Son ventas brutas (no la comisión del sitio), calculadas con el
                        contador público de vendidos, que no está auditado.
                    </template>
                    <template v-else>Primera foto de este sitio: todavía no hay con qué comparar.</template>
                </div>

                <div v-if="chips.length" class="active">
                    Filtros activos:
                    <span v-for="[k, etiqueta] in chips" :key="k" class="chip">{{ etiqueta }}<button aria-label="Quitar" @click="quitarChip(k)">×</button></span>
                </div>

                <template v-if="!verRetiradas">
                    <div v-if="!filtradas.length" class="empty">
                        <b>Ninguna oferta coincide con estos filtros.</b><br><br>
                        <button class="btn" @click="limpiar">Limpiar filtros</button>
                    </div>
                    <OfertasTabla v-else :ofertas="visibles" :desde="desde" :comparada="comparada" :orden="orden" :asc="asc" @ordenar="ordenarPor" />

                    <button v-if="filtradas.length > limite" class="btn more" @click="limite += 60">
                        Mostrar más ({{ filtradas.length - limite }} restantes)
                    </button>
                </template>

                <div v-if="verRetiradas && !retiradas.length" class="empty">
                    <b>Ninguna oferta retirada {{ desde }} coincide con estos filtros.</b>
                </div>
                <div v-if="retiradas.length" class="ret" :class="{ 'ret-arriba': verRetiradas }">
                    <h2>Retiradas {{ desde }} ({{ retiradas.length }})</h2>
                    <p v-if="verRetiradas" class="tagline">Estaban en la foto anterior y ya no están. Precio y vendidos: los de la foto anterior.</p>
                    <div class="tablewrap">
                        <table>
                            <thead><tr><th>Oferta</th><th>Precio</th><th>Vendidos</th></tr></thead>
                            <tbody>
                                <tr v-for="o in retiradas" :key="o.id">
                                    <td class="t"><a :href="o.url" target="_blank">{{ o.titulo }}</a><small>{{ o.marca }} · {{ o.categoria }} · ID {{ o.id }}</small></td>
                                    <td class="n">{{ sol(o.precio_oferta) }}</td>
                                    <td class="n">{{ entero(o.vendidos) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </template>
</template>
