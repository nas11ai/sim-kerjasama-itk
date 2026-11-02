<script setup lang="ts" generic="K extends string">
import FilterSelect from '@/Components/app/FilterSelect.vue'
import { Input } from '@/Components/ui/input'
import type { FilterSelectSpec } from '@/types/submission-page'

// Pencarian + filter tabel yang disaring server (fe-v2-design §4.2); state dipegang useFilters halaman.
defineProps<{
    selects: FilterSelectSpec<K>[]
    values: Record<K, string>
    searchPlaceholder: string
    searchLabel: string
}>()

const search = defineModel<string>('search', { required: true })
const emit = defineEmits<{ select: [key: K, value: string] }>()
</script>

<template>
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
        <Input
            v-model="search"
            type="search"
            :placeholder="searchPlaceholder"
            :aria-label="searchLabel"
            class="sm:max-w-xs"
        />
        <FilterSelect
            v-for="select in selects"
            :key="select.key"
            :model-value="values[select.key]"
            :options="select.options"
            :all-label="select.allLabel"
            :label="select.label"
            @update:model-value="emit('select', select.key, $event)"
        />
    </div>
</template>
