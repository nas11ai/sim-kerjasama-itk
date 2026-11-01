<script setup lang="ts">
// `aside`: panel di samping kartu (pengumuman publik di halaman login, W6).
// `wide`: kartu lebih lebar untuk bacaan panjang (pengumuman yang dibuka tamu).
defineProps<{ wide?: boolean }>()
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-muted/40 p-4">
        <div
            class="w-full"
            :class="$slots.aside ? 'grid max-w-4xl items-start gap-6 md:grid-cols-2' : 'contents'"
        >
            <div
                class="w-full rounded-lg border bg-card p-8 shadow-sm"
                :class="wide ? 'max-w-2xl' : 'max-w-md'"
            >
                <div class="mb-6 text-center">
                    <div class="text-2xl font-semibold">SIMPAS v2</div>
                    <div class="text-sm text-muted-foreground">LPPM ITK</div>
                </div>
                <slot />
            </div>
            <slot name="aside" />
        </div>
    </div>
</template>
