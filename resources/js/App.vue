<script setup>
import { onMounted, ref } from 'vue';
import { leerIndice } from './lib/datos';
import { capitalizar } from './lib/formato';

// El menu lista los sitios que tienen fotos en la base.
const sitios = ref([]);
onMounted(async () => {
    try {
        sitios.value = Object.keys((await leerIndice()).sitios);
    } catch {
        // Sin indice el menu queda solo con "Inicio"; la pagina de inicio muestra el aviso.
    }
});
</script>

<template>
    <nav class="nav">
        <RouterLink to="/" class="logo">ARGOS</RouterLink>
        <RouterLink v-for="s in sitios" :key="s" :to="`/ofertas/${s}`" class="link">{{ capitalizar(s) }}</RouterLink>
        <RouterLink to="/comparar" class="link">Ofertas en común</RouterLink>
    </nav>
    <!-- Aqui vue-router pinta la pagina que corresponda a la URL. -->
    <RouterView />
</template>
