<script setup lang="ts">
// Frame: ADMIN-08-04
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'
import { computed } from 'vue'

import PageHeader from '@/Components/app/PageHeader.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import SchemeExtraRulesCard from '@/Components/Scheme/SchemeExtraRulesCard.vue'
import SchemeMainRulesCard from '@/Components/Scheme/SchemeMainRulesCard.vue'
import TrlPicker from '@/Components/Scheme/TrlPicker.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { SchemeFormPageProps, SchemeMainFields, SchemeRuleFields } from '@/types/scheme'

const props = defineProps<SchemeFormPageProps>()

const text = (value: number | null | undefined) =>
    value === null || value === undefined ? '' : String(value)

const form = useForm({
    name: props.scheme?.name ?? '',
    code: props.scheme?.code ?? '',
    scheme_type_id: props.scheme?.schemeTypeId == null ? null : String(props.scheme.schemeTypeId),
    submission_type_id:
        props.scheme?.submissionTypeId == null ? null : String(props.scheme.submissionTypeId),
    max_budget: text(props.scheme?.maxBudget),
    max_members: text(props.scheme?.maxMembers),
    duration_months: text(props.scheme?.durationMonths),
    // W108 — layar ini menyunting aturan tambahan dan TRL, jadi keduanya selalu dikirim.
    rules: {
        min_reviewer_count: text(props.scheme?.rules.min_reviewer_count),
        max_reviewer_workload: text(props.scheme?.rules.max_reviewer_workload),
        max_student_members: text(props.scheme?.rules.max_student_members),
        allowed_output_types: [...(props.scheme?.rules.allowed_output_types ?? [])],
    } satisfies SchemeRuleFields,
    allowed_trl_ids: [...(props.scheme?.allowedTrlIds ?? [])],
})

const main = computed({
    get: (): SchemeMainFields => ({
        name: form.name,
        code: form.code,
        scheme_type_id: form.scheme_type_id,
        submission_type_id: form.submission_type_id,
        max_budget: form.max_budget,
        max_members: form.max_members,
        duration_months: form.duration_months,
    }),
    set: (fields: SchemeMainFields) => Object.assign(form, fields),
})

const title = computed(() =>
    props.scheme === null ? 'Buat Skema Baru' : `Edit Skema — ${props.scheme.name}`
)

function save() {
    if (props.scheme === null) {
        form.post(route('admin.schemes.store'), { preserveScroll: true })
        return
    }
    form.put(route('admin.schemes.update', props.scheme.id), { preserveScroll: true })
}
</script>

<template>
    <Head :title="title" />
    <AppLayout>
        <PageHeader
            :title="title"
            subtitle="Atur ketentuan skema: batasan utama, aturan tambahan, dan TRL yang diizinkan."
            :back-href="route('admin.schemes.index')"
            back-label="Daftar Skema"
        >
            <!-- W106 — status berubah lewat Nonaktifkan/Aktifkan di daftar, bukan di form ini -->
            <template v-if="scheme" #badge>
                <Badge :variant="scheme.isActive ? 'success' : 'outline'">{{
                    scheme.isActive ? 'Aktif' : 'Nonaktif'
                }}</Badge>
            </template>
        </PageHeader>
        <form class="max-w-3xl space-y-6" @submit.prevent="save">
            <SchemeMainRulesCard v-model="main" :options="options" :errors="form.errors" />
            <SchemeExtraRulesCard v-model="form.rules" :options="options" :errors="form.errors" />
            <SectionCard title="TRL yang Diizinkan">
                <TrlPicker v-model="form.allowed_trl_ids" :options="options.trls" />
            </SectionCard>
            <div class="flex items-center justify-between">
                <Button as-child variant="ghost">
                    <Link :href="route('admin.schemes.index')">Batalkan</Link>
                </Button>
                <Button type="submit" :disabled="form.processing"
                    ><Check class="size-4" />Simpan</Button
                >
            </div>
        </form>
    </AppLayout>
</template>
