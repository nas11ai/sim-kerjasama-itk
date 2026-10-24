<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ArrowLeft } from 'lucide-vue-next'

defineProps<{
    title: string
    subtitle?: string
    backHref?: string
    backLabel?: string
}>()
</script>

<template>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <Link
                v-if="backHref"
                :href="backHref"
                class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                {{ backLabel ?? 'Kembali' }}
            </Link>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                <slot name="badge" />
            </div>
            <p v-if="subtitle" class="mt-1 text-sm text-muted-foreground">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
