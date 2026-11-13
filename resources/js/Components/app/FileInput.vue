<script setup lang="ts">
import { computed, ref } from 'vue'
import { Upload } from 'lucide-vue-next'

import { buttonVariants } from '@/Components/ui/button'
import { cn } from '@/lib/utils'

// Paket 2 P1 — tombol bawaan <input type="file"> mengikuti bahasa peramban ("Choose File",
// "No file chosen") dan tidak bisa diterjemahkan. Input asli tetap ada (sr-only) supaya
// keyboard, label FormField, dan handler @change pemanggil bekerja seperti biasa; yang
// terlihat hanya tombol "Pilih berkas" dan nama berkas yang sedang terpilih.
defineOptions({ inheritAttrs: false })

const props = withDefaults(
    defineProps<{
        id: string
        multiple?: boolean
        disabled?: boolean
    }>(),
    { multiple: false, disabled: false }
)

const emit = defineEmits<{ change: [event: Event] }>()

const picked = ref<string[]>([])
const summary = computed(() => {
    if (picked.value.length === 0) return ''

    return picked.value.length === 1 ? picked.value[0] : `${picked.value.length} berkas dipilih`
})

function onChange(event: Event) {
    emit('change', event)

    // Dibaca setelah handler pemanggil: yang mengosongkan input (unggah langsung, daftar
    // berkas sendiri) tidak meninggalkan nama berkas basi di sini.
    const input = event.target
    picked.value =
        input instanceof HTMLInputElement ? Array.from(input.files ?? [], (f) => f.name) : []
}
</script>

<template>
    <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
        <input
            :id="props.id"
            v-bind="$attrs"
            type="file"
            class="peer sr-only"
            :multiple="props.multiple"
            :disabled="props.disabled"
            @change="onChange"
        />
        <label
            :for="props.id"
            :class="
                cn(
                    buttonVariants({ variant: 'outline', size: 'sm' }),
                    'cursor-pointer peer-focus-visible:ring-2 peer-focus-visible:ring-ring peer-focus-visible:ring-offset-2 peer-disabled:cursor-not-allowed peer-disabled:opacity-50'
                )
            "
        >
            <Upload aria-hidden="true" />
            Pilih berkas
        </label>
        <!-- Region live dirender sejak awal supaya pembaca layar mengumumkan perubahannya. -->
        <span class="min-w-0 truncate text-sm text-muted-foreground" aria-live="polite">
            {{ summary }}
        </span>
    </div>
</template>
