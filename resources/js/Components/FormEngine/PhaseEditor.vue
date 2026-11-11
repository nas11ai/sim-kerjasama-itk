<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import PhaseDetailList from '@/Components/FormEngine/PhaseDetailList.vue'
import PhaseGeneralCard from '@/Components/FormEngine/PhaseGeneralCard.vue'
import { Button } from '@/Components/ui/button'
import {
    blankDetail,
    toEditableDetails,
    toPhasePayload,
    type PhaseGeneral,
} from '@/lib/phaseDefinition'
import type { PhaseEditPageProps } from '@/types/form-engine'

// Trap S2b — state editor hidup di sini; halaman memberinya :key per tahap.
const props = defineProps<Omit<PhaseEditPageProps, 'evaluation' | 'fieldTypeOptions'>>()

// W94 + BR-FE-28 — mode buat: satu detail Pengajuan kosong berakses pilihan pertama; tanpa pilihan,
// tak bisa disimpan.
const creating = props.phase.id === null
const firstRule = props.accessOptions[0]
const initialDetails = () =>
    creating && firstRule
        ? [blankDetail(Number(firstRule.value), Number(props.phaseTypeOptions[0]?.value ?? 0))]
        : toEditableDetails(props.phase.details)

const editor = useForm({
    title: props.phase.title,
    description: props.phase.description ?? '',
    isActive: props.phase.isActive,
    details: initialDetails(),
})

const general = computed({
    get: (): PhaseGeneral => ({
        title: editor.title,
        description: editor.description,
        isActive: editor.isActive,
    }),
    set: (value: PhaseGeneral) => Object.assign(editor, value),
})

// Galat baris `phase_details.{i}.*` tampil di barisnya; galat kartu Tahap di kartunya.
const OWN_KEYS = ['title', 'description', 'is_active']
const errors = computed<Record<string, string>>(() => ({ ...editor.errors }))
const otherErrors = computed(() =>
    Object.entries(errors.value)
        .filter(([key]) => !OWN_KEYS.includes(key) && !key.startsWith('phase_details.'))
        .map(([, message]) => message)
)

function save() {
    const pending = editor.transform((data) => toPhasePayload(data, data.details))
    if (props.phase.id === null) {
        pending.post(route('admin.phases.store'))
        return
    }
    pending.put(route('admin.phases.update', props.phase.id))
}
</script>

<template>
    <form class="max-w-3xl space-y-6" @submit.prevent="save">
        <AppBanner v-if="creating && !firstRule" tone="warning">
            Belum ada Form aktif yang punya aturan akses. Buat aturan akses di Form Builder lebih
            dulu.
            <Link class="underline" :href="route('admin.forms.index')">Buka Form Builder</Link>
        </AppBanner>
        <PhaseGeneralCard v-model="general" :errors="errors" />
        <AppBanner v-if="otherErrors.length > 0" tone="denial">
            <p v-for="message in otherErrors" :key="message">{{ message }}</p>
        </AppBanner>
        <PhaseDetailList
            v-model="editor.details"
            :saved="phase.details"
            :access-options="accessOptions"
            :phase-type-options="phaseTypeOptions"
            :errors="errors"
        />
        <div class="flex flex-wrap justify-between gap-2">
            <Button as-child variant="ghost">
                <Link :href="route('admin.phases.index')">Batalkan</Link>
            </Button>
            <Button type="submit" :disabled="editor.processing || (creating && !firstRule)"
                ><Check class="size-4" />Simpan Tahap</Button
            >
        </div>
    </form>
</template>
