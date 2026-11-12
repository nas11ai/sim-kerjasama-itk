<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'
import { computed } from 'vue'

import PhaseGeneralCard from '@/Components/FormEngine/PhaseGeneralCard.vue'
import { Button } from '@/Components/ui/button'
import type { PhaseGeneral } from '@/lib/phaseDefinition'
import type { PhaseEditData } from '@/types/form-engine'

// BR-FE-33 — judul, deskripsi, dan status aktif bukan isi berversi: disimpan langsung, tanpa draf.
const props = defineProps<{ phaseId: number; phase: PhaseEditData }>()

const general = useForm({
    title: props.phase.title,
    description: props.phase.description ?? '',
    isActive: props.phase.isActive,
})
const model = computed({
    get: (): PhaseGeneral => ({
        title: general.title,
        description: general.description,
        isActive: general.isActive,
    }),
    set: (value: PhaseGeneral) => Object.assign(general, value),
})
const errors = computed<Record<string, string>>(() => ({ ...general.errors }))

const save = () =>
    general
        .transform((data) => ({
            title: data.title,
            description: data.description,
            is_active: data.isActive,
        }))
        .put(route('admin.phases.update', props.phaseId), { preserveScroll: true })
</script>

<template>
    <form class="space-y-3" @submit.prevent="save">
        <PhaseGeneralCard v-model="model" :errors="errors" />
        <div class="flex justify-end">
            <Button type="submit" variant="outline" :disabled="general.processing"
                ><Check class="size-4" />Simpan Tahap</Button
            >
        </div>
    </form>
</template>
