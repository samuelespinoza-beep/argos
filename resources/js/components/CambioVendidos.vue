<script setup>
import { entero, sol } from '../lib/formato';

defineProps({
    oferta: { type: Object, required: true },
    desde: { type: String, required: true },
    monto: { type: Boolean, default: true },
});
</script>

<template>
    <span v-if="oferta.nueva" class="delta up">nueva</span>
    <span v-else-if="oferta.delta_vend > 0" class="delta up">
        +{{ entero(oferta.delta_vend) }} {{ desde }}<template v-if="monto && oferta.ingreso"> · ≈ {{ sol(oferta.ingreso) }}</template>
    </span>
    <span v-else-if="oferta.delta_vend < 0" class="delta down">{{ entero(oferta.delta_vend) }}</span>
    <span v-else class="delta flat">sin cambio</span>
</template>
