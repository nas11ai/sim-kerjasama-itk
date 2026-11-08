<script setup lang="ts">
import { Toaster } from '@/Components/ui/toast'
import { useFlashToasts } from '@/composables/useFlashToasts'

// `aside`: panel di samping kartu (pengumuman publik di halaman login, W6).
// `wide`: kartu lebih lebar untuk bacaan panjang (pengumuman yang dibuka tamu).
defineProps<{ wide?: boolean }>()

// W163 — form tamu (login, daftar) juga bisa kena 419; toast "Sesi kedaluwarsa" harus tampil.
useFlashToasts()
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
        <Toaster />
    </div>
</template>
