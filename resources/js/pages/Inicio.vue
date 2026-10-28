<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { capitalizar, dia, fechaHora } from '../lib/formato';

defineProps({
    indice: { type: Object, required: true }, // { actualizado, propio, sitios: { cuponatic: ['2026-10-28', ...] }, competidores }
});
</script>

<template>
    <Head title="Inicio" />
    <div class="home">
        <h1>Argos</h1>
        <p class="sub">Ofertas de Cuponidad y la competencia, foto diaria a las 06:00.</p>

        <div v-if="!Object.keys(indice.sitios).length" class="aviso">Todavía no hay fotos en la base. Corre "make scrape".</div>
        <template v-else>
            <div class="sitios">
                <Link v-for="(fechas, sitio) in indice.sitios" :key="sitio" :href="`/ofertas/${sitio}`" class="sitio">
                    <span class="tag">{{ sitio === indice.propio ? 'Propio' : 'Competencia' }}</span>
                    <h2>{{ capitalizar(sitio) }}</h2>
                    <p>Última foto: <b>{{ dia(fechas[0]) }}</b> · {{ fechas.length }} {{ fechas.length === 1 ? 'foto' : 'fotos' }}</p>
                </Link>
            </div>
            <p v-if="indice.actualizado" class="tagline" style="margin-top: 16px">Último scraping: {{ fechaHora(indice.actualizado) }}</p>
        </template>
    </div>
</template>
