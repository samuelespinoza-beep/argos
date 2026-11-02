<script setup>
defineProps({ opciones: { type: Array, required: true } });
const valor = defineModel();
</script>

<template>
    <div class="pills">
        <button v-for="[v, etiqueta] in opciones" :key="v" type="button" :class="{ on: v === valor }" @click="valor = v">
            {{ etiqueta }}
        </button>
    </div>
</template>
