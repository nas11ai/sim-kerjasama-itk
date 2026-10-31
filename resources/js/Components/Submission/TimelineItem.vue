<script setup lang="ts">
import { formatDate } from '@/lib/format'

// Satu titik timeline audit: judul, isi opsional, lalu "tanggal · aktor".
defineProps<{ title: string; at: string | null; actor: string | null }>()
</script>

<template>
    <li class="relative">
        <span class="absolute top-1.5 -left-[1.3rem] size-2 rounded-full bg-primary" />
        <p class="font-medium">{{ title }}</p>
        <slot />
        <p class="text-xs text-muted-foreground">
            {{ [formatDate(at), actor].filter(Boolean).join(' · ') }}
        </p>
    </li>
</template>
