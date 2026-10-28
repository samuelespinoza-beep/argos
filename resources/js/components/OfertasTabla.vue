<script setup>
import { sol } from '../lib/formato';
import CambioPrecio from './CambioPrecio.vue';
import CambioVendidos from './CambioVendidos.vue';

defineProps({
    ofertas: { type: Array, required: true },
    desde: { type: String, required: true },
    comparada: { type: Boolean, default: false },
    orden: { type: String, required: true },
    asc: { type: Boolean, default: false },
});
const emit = defineEmits(['ordenar']);

// [campo, etiqueta, centrada]
const columnas = [
    ['', ''], ['titulo', 'Oferta'], ['precio_oferta', 'Precio', true],
    ['delta_vend', 'Vendidos', true], ['ingreso', 'Total', true],
];
</script>

<template>
    <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th v-for="[k, etiqueta, centrada] in columnas" :key="k" :class="{ sort: k, centro: centrada, on: k && k === orden, asc: k === orden && asc }" @click="k && emit('ordenar', k)">
                        {{ etiqueta }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="o in ofertas" :key="o.id">
                    <td><img :src="o.imagen" loading="lazy" alt=""></td>
                    <td class="t">
                        <a :href="o.url" target="_blank">{{ o.titulo }}</a>
                        <small>{{ o.marca }} · {{ o.categoria }}<template v-if="o.subcategoria"> / {{ o.subcategoria }}</template> · ID {{ o.id }}</small>
                    </td>
                    <td class="n centro"><span class="price">{{ sol(o.precio_oferta) }}</span><br><CambioPrecio :oferta="o" /></td>
                    <td class="n centro"><CambioVendidos v-if="comparada" :oferta="o" :desde="desde" :monto="false" /></td>
                    <td class="n centro"><b v-if="o.ingreso">{{ sol(o.ingreso) }}</b><template v-else>—</template></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
.centro { text-align: center; }
</style>
