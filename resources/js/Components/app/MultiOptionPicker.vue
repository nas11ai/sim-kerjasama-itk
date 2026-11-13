<script setup lang="ts">
import { Search, X } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import { Button } from '@/Components/ui/button'
import { Checkbox } from '@/Components/ui/checkbox'
import { Input } from '@/Components/ui/input'

export interface PickerOption {
    value: string
    label: string
    /** tingkat pohon; 0 = akar */
    depth?: number
    disabled?: boolean
    /** alasan item nonaktif, tampil di samping label */
    hint?: string
}

// K6 (2026-11-13) — banyak pilihan sekaligus: cari, centang, lepas lewat chip. Daftar tetap
// di halaman (bukan popover) supaya pohon organisasi terbaca utuh saat memilih.
const props = defineProps<{ id: string; options: PickerOption[]; searchPlaceholder?: string }>()
const selected = defineModel<string[]>({ required: true })

const query = ref('')

const visible = computed(() => {
    const needle = query.value.trim().toLocaleLowerCase('id')
    if (needle === '') return props.options

    return props.options.filter((option) => option.label.toLocaleLowerCase('id').includes(needle))
})

const chosen = computed(() =>
    props.options.filter((option) => selected.value.includes(option.value))
)

function toggle(value: string, checked: boolean | 'indeterminate') {
    selected.value =
        checked === true
            ? [...selected.value.filter((item) => item !== value), value]
            : selected.value.filter((item) => item !== value)
}
</script>

<template>
    <div class="flex min-w-0 flex-col gap-2">
        <div v-if="chosen.length > 0" class="flex flex-wrap gap-1.5" aria-live="polite">
            <span
                v-for="option in chosen"
                :key="option.value"
                class="inline-flex max-w-full items-center gap-1 rounded-md border bg-muted px-2 py-0.5 text-xs"
            >
                <span class="truncate">{{ option.label }}</span>
                <button
                    type="button"
                    class="rounded-sm text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :aria-label="`Lepas ${option.label}`"
                    @click="toggle(option.value, false)"
                >
                    <X class="size-3" aria-hidden="true" />
                </button>
            </span>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-6 px-2 text-xs"
                @click="selected = []"
                >Kosongkan</Button
            >
        </div>
        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                :id="id"
                v-model="query"
                type="search"
                class="pl-8"
                :placeholder="searchPlaceholder ?? 'Cari...'"
                :aria-controls="`${id}-list`"
            />
        </div>
        <ul
            :id="`${id}-list`"
            class="max-h-64 overflow-y-auto rounded-md border p-1"
            :aria-label="`${chosen.length} dipilih`"
        >
            <li v-for="option in visible" :key="option.value">
                <label
                    :for="`${id}-option-${option.value}`"
                    class="flex cursor-pointer items-center gap-2 rounded-sm py-1.5 pr-2 text-sm hover:bg-accent has-[[data-disabled]]:cursor-not-allowed has-[[data-disabled]]:opacity-60"
                    :style="{
                        paddingLeft: `${0.5 + (query === '' ? (option.depth ?? 0) : 0) * 1.25}rem`,
                    }"
                >
                    <Checkbox
                        :id="`${id}-option-${option.value}`"
                        :model-value="selected.includes(option.value)"
                        :disabled="option.disabled"
                        @update:model-value="(checked) => toggle(option.value, checked)"
                    />
                    <span class="min-w-0 flex-1 truncate">{{ option.label }}</span>
                    <span v-if="option.hint" class="shrink-0 text-xs text-muted-foreground">{{
                        option.hint
                    }}</span>
                </label>
            </li>
            <li v-if="visible.length === 0" class="px-2 py-1.5 text-sm text-muted-foreground">
                Tidak ada yang cocok.
            </li>
        </ul>
    </div>
</template>
