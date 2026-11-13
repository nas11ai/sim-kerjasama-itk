<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { ArrowRight } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import FormField from '@/Components/app/FormField.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { formatDate } from '@/lib/format'
import type { StartPeriodOption, WizardSubmission } from '@/types/submission-wizard'

const props = defineProps<{ submission: WizardSubmission | null; options: StartPeriodOption[] }>()

const page = usePage()
const periodId = ref<string | null>(null)
const formId = ref<string | null>(null)
const processing = ref(false)

const period = computed(() => props.options.find((p) => String(p.id) === periodId.value) ?? null)
const form = computed(
    () => period.value?.forms.find((f) => String(f.formId) === formId.value) ?? null
)

const periodOptions = computed(() =>
    props.options.map((p) => ({
        value: String(p.id),
        label: p.closesAt ? `${p.name}, dibuka s.d. ${formatDate(p.closesAt)}` : p.name,
    }))
)
const formOptions = computed(() =>
    (period.value?.forms ?? []).map((f) => ({ value: String(f.formId), label: f.title }))
)

const draftHref = computed(() =>
    form.value?.draftId ? route('user.submissions.wizard', form.value.draftId) : null
)

watch(periodId, () => (formId.value = null))

// ADR 0012 — langkah 1 melahirkan baris pengajuan; backend mengarahkan ke langkah 2.
const start = () => {
    if (period.value === null || form.value === null) return
    router.post(
        route('user.submissions.store'),
        {
            submission_period_id: period.value.id,
            form_id: form.value.formId,
            form_phase_id: form.value.formPhaseId,
        },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
        }
    )
}
</script>

<template>
    <SectionCard title="Step 1: Pilih Form & Periode">
        <template v-if="submission">
            <InfoGrid
                :items="[
                    { label: 'Periode Pengajuan', value: submission.period ?? '-' },
                    { label: 'Form Pengajuan', value: submission.form },
                ]"
            />
            <p class="text-sm text-muted-foreground">
                Form dan periode tidak dapat diganti setelah draft dibuat.
            </p>
        </template>
        <EmptyState
            v-else-if="options.length === 0"
            title="Belum ada periode pengajuan yang dibuka."
        />
        <template v-else>
            <FormField id="start-period" label="Periode Pengajuan" required>
                <OptionSelect id="start-period" v-model="periodId" :options="periodOptions" />
            </FormField>
            <FormField
                id="start-form"
                label="Form Pengajuan"
                required
                hint="Hanya form yang dapat Anda akses sesuai organisasi Anda"
            >
                <OptionSelect
                    id="start-form"
                    v-model="formId"
                    :options="formOptions"
                    :disabled="period === null"
                />
            </FormField>
            <!-- BR-IAM-03 — profil yang belum lengkap menahan pembuatan pengajuan -->
            <AppBanner
                v-if="
                    page.props.errors.profile ||
                    page.props.errors.form_id ||
                    page.props.errors.form_phase_id
                "
                tone="denial"
            >
                {{
                    page.props.errors.profile ??
                    page.props.errors.form_id ??
                    page.props.errors.form_phase_id
                }}
            </AppBanner>
            <div class="flex justify-end">
                <Button v-if="draftHref" as-child variant="outline">
                    <Link :href="draftHref"> Lanjutkan draft <ArrowRight class="size-4" /> </Link>
                </Button>
                <Button
                    v-else
                    data-action="start"
                    :disabled="form === null || processing"
                    @click="start"
                >
                    Lanjut <ArrowRight class="size-4" />
                </Button>
            </div>
        </template>
        <!-- BR-SM-10 -->
        <AppBanner tone="info">
            Jika periode ditutup saat Anda masih mengisi, draft tetap tersimpan namun tidak dapat
            disubmit.
        </AppBanner>
    </SectionCard>
</template>
