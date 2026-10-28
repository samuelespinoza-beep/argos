<script setup>
import { sol, vacio } from '../lib/formato';

defineProps({ oferta: { type: Object, required: true } });
</script>

<template>
    <span v-if="!vacio(oferta.delta_precio)" :class="oferta.delta_precio < 0 ? 'up' : 'down'" :title="`Antes ${sol(oferta.precio_ant)}`">
        {{ oferta.delta_precio < 0 ? '▼' : '▲' }} {{ sol(Math.abs(oferta.delta_precio)) }}
    </span>
</template>
