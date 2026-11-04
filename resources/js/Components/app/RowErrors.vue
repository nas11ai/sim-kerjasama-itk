<script setup lang="ts">
// Galat validasi satu baris daftar, satu pesan per baris teks.
defineProps<{ messages: string[] }>()
</script>

<template>
    <p v-for="message in messages" :key="message" class="text-xs text-destructive">
        {{ message }}
    </p>
</template>
