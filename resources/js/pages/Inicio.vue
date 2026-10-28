<script setup>
import { onMounted, ref } from 'vue';
import { leerIndice } from '../lib/datos';
import { capitalizar, dia, fechaHora } from '../lib/formato';

const indice = ref(null);
const error = ref('');

onMounted(async () => {
    try {
        indice.value = await leerIndice();
    } catch (e) {
        error.value = e.message;
    }
});
</script>

<template>
    <div class="home">
        <h1>Argos</h1>
        <p class="sub">Ofertas de Cuponidad y la competencia, foto diaria a las 06:00.</p>

        <div v-if="error" class="aviso">{{ error }}</div>
        <div v-else-if="!indice" class="cargando">Cargando…</div>
        <template v-else>
            <div class="sitios">
                <RouterLink v-for="(fechas, sitio) in indice.sitios" :key="sitio" :to="`/ofertas/${sitio}`" class="sitio">
                    <span class="tag">{{ sitio === indice.propio ? 'Propio' : 'Competencia' }}</span>
                    <h2>{{ capitalizar(sitio) }}</h2>
                    <p>Última foto: <b>{{ dia(fechas[0]) }}</b> · {{ fechas.length }} {{ fechas.length === 1 ? 'foto' : 'fotos' }}</p>
                </RouterLink>
            </div>
            <p v-if="indice.actualizado" class="tagline" style="margin-top: 16px">Último scraping: {{ fechaHora(indice.actualizado) }}</p>
        </template>
    </div>
</template>
