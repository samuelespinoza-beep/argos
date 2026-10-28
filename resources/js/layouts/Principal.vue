<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { capitalizar } from '../lib/formato';

const page = usePage();
const sitios = computed(() => page.props.sitios ?? []);
const activo = (prefijo) => page.url === prefijo || page.url.startsWith(`${prefijo}/`);
</script>

<template>
    <nav class="nav">
        <Link href="/" class="logo">ARGOS</Link>
        <Link v-for="s in sitios" :key="s" :href="`/ofertas/${s}`" class="link" :class="{ activo: activo(`/ofertas/${s}`) }">{{ capitalizar(s) }}</Link>
        <Link href="/comparar" class="link" :class="{ activo: activo('/comparar') }">Ofertas en común</Link>
    </nav>
    <slot />
</template>
