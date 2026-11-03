<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

import type { FormHeaderData } from '@/types/form-engine'

const props = defineProps<{ form: FormHeaderData | null; active: 'general' | 'access' }>()

type Tab = { key: string; label: string; href: string | null; title?: string }

// W86 — tab = route. Mode buat: tab lain menunggu Form tersimpan; tab Phases menaut ke
// layar tahap legacy sampai penggantinya ada.
const tabs = computed((): Tab[] => {
    const form = props.form
    if (form === null) {
        const title = 'Simpan form lebih dulu'
        return [
            { key: 'general', label: 'General & Fields', href: route('admin.forms.create') },
            { key: 'access', label: 'Access Control', href: null, title },
            { key: 'phases', label: 'Phases', href: null, title },
        ]
    }
    return [
        { key: 'general', label: 'General & Fields', href: route('admin.forms.edit', form.id) },
        { key: 'access', label: 'Access Control', href: null },
        { key: 'phases', label: 'Phases', href: route('admin.form-phases.index') },
    ]
})

const base = 'inline-flex items-center rounded-md px-3 py-1.5 text-sm font-medium'
</script>

<template>
    <nav
        aria-label="Bagian form"
        class="mb-6 inline-flex flex-wrap items-center gap-1 rounded-lg bg-muted p-1 text-muted-foreground"
    >
        <template v-for="tab in tabs" :key="tab.key">
            <Link
                v-if="tab.href !== null"
                :href="tab.href"
                :aria-current="tab.key === active ? 'page' : undefined"
                :class="[
                    base,
                    tab.key === active
                        ? 'bg-background text-foreground shadow-sm'
                        : 'hover:text-foreground',
                ]"
                >{{ tab.label }}</Link
            >
            <span
                v-else
                aria-disabled="true"
                :title="tab.title"
                :class="[base, 'cursor-not-allowed opacity-50']"
                >{{ tab.label }}</span
            >
        </template>
    </nav>
</template>
