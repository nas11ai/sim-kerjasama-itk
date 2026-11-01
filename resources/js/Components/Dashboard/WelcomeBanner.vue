<script setup lang="ts">
// Frame: SHARED-02-01..04
defineProps<{ name: string; subtitle: string | null }>()
</script>

<template>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight">Selamat datang, {{ name }}!</h1>
        <p class="mt-1 text-sm text-muted-foreground">
            {{ subtitle ?? 'Ringkasan pengajuan dan aktivitas terbaru Anda.' }}
        </p>
    </div>
</template>
