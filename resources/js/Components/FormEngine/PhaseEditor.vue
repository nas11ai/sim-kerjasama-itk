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
const props = defineProps<PhaseEditPageProps>()

// W94 — mode buat: satu detail kosong berakses aturan pertama Form ini; tanpa aturan, tak bisa disimpan.
const creating = props.phase.id === null
const ownRules = props.accessOptions.filter((option) => option.formId === props.form.id)
const firstRule = ownRules[0]
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
        pending.post(route('admin.forms.phases.store', props.form.id))
        return
    }
    pending.put(
        route('admin.forms.phases.update', { form: props.form.id, formPhase: props.phase.id })
    )
}
</script>

<template>
    <form class="max-w-3xl space-y-6" @submit.prevent="save">
        <AppBanner v-if="creating && !firstRule" tone="warning">
            Tambahkan aturan akses Form ini lebih dulu — detail tahap memakai aturan akses sebagai
            pengisinya.
            <Link class="underline" :href="route('admin.forms.access.index', form.id)"
                >Buka Access Control</Link
            >
        </AppBanner>
        <PhaseGeneralCard v-model="general" :errors="errors" />
        <AppBanner v-if="otherErrors.length > 0" tone="denial">
            <p v-for="message in otherErrors" :key="message">{{ message }}</p>
        </AppBanner>
        <PhaseDetailList
            v-model="editor.details"
            :form-id="form.id"
            :saved="phase.details"
            :access-options="accessOptions"
            :phase-type-options="phaseTypeOptions"
            :deadline-options="deadlineOptions"
            :errors="errors"
        />
        <div class="flex flex-wrap justify-between gap-2">
            <Button as-child variant="ghost">
                <Link :href="route('admin.forms.phases.index', form.id)">Batalkan</Link>
            </Button>
            <Button type="submit" :disabled="editor.processing || (creating && !firstRule)"
                ><Check class="size-4" />Simpan Tahap</Button
            >
        </div>
    </form>
</template>
