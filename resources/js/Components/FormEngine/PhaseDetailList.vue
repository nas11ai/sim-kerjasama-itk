<script setup lang="ts">
import { Pencil, Plus, Trash2 } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import SectionCard from '@/Components/app/SectionCard.vue'
import PhaseDetailDialog from '@/Components/FormEngine/PhaseDetailDialog.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { blankDetail, type EditableDetail } from '@/lib/phaseDefinition'
import type {
    PhaseAccessOption,
    PhaseDetailEditData,
    SubmissionDateOption,
} from '@/types/form-engine'
import type { FilterOption } from '@/types/submission-page'

const props = defineProps<{
    formId: number
    saved: PhaseDetailEditData[]
    accessOptions: PhaseAccessOption[]
    phaseTypeOptions: FilterOption[]
    deadlineOptions: SubmissionDateOption[]
    errors: Record<string, string>
}>()
const details = defineModel<EditableDetail[]>({ required: true })

const editing = ref<EditableDetail | null>(null)
const dialogOpen = ref(false)

// W93 — label baris dari backend: label tersimpan selama aturan aksesnya belum diganti.
const savedByAccess = computed(
    () => new Map(props.saved.map((detail) => [detail.formAccessControlId, detail]))
)
const typeName = (id: number) =>
    props.phaseTypeOptions.find((option) => option.value === String(id))?.label ?? ''
const heading = (detail: EditableDetail) => {
    const saved = savedByAccess.value.get(detail.formAccessControlId)
    if (saved)
        return {
            title: `${typeName(detail.phaseTypeId)} — ${saved.formTitle}`,
            access: saved.accessLabel,
        }
    const access = props.accessOptions.find((o) => o.value === String(detail.formAccessControlId))
    return {
        title: [typeName(detail.phaseTypeId), access?.formTitle].filter(Boolean).join(' — '),
        access: access?.label ?? '',
    }
}
// W96 — nomor urut diturunkan backend dari Form; detail baru belum punya.
const orderOf = (detail: EditableDetail) =>
    props.saved.find((saved) => saved.id === detail.id)?.order ?? 'Baru'

const dateLabel = (detail: EditableDetail, id: number | null) =>
    [...props.deadlineOptions, ...detail.currentDeadlines].find((o) => o.id === id)?.label ?? '—'
const deadlines = (detail: EditableDetail) =>
    [
        `Batas Submit: ${dateLabel(detail, detail.submissionDateId)}`,
        detail.needsReview ? `Batas Evaluasi: ${dateLabel(detail, detail.evaluationDateId)}` : null,
        detail.revisionDateId ? `Batas Revisi: ${dateLabel(detail, detail.revisionDateId)}` : null,
    ].filter((line) => line !== null)

// W98 / BR-FE-22 — hapus terkunci oleh hitungan backend, dengan angkanya.
const removeHint = (detail: EditableDetail) => {
    if (detail.submissionCount > 0)
        return `Dipakai ${detail.submissionCount} pengajuan — nonaktifkan tahapnya bila detail ini tidak boleh dipakai lagi.`
    if (detail.assignmentCount > 0)
        return `Form evaluasinya pernah ditugaskan ${detail.assignmentCount} kali.`
    return undefined
}
const rowErrors = (index: number) =>
    Object.entries(props.errors).flatMap(([key, message]) =>
        key.startsWith(`phase_details.${index}.`) ? [message] : []
    )

// W97 — kartu form evaluasi menyimpan sendiri lalu props dimuat ulang; `saved` selalu terbaru.
const evaluationFormsOf = (detail: EditableDetail | null) =>
    props.saved.find((saved) => saved.id === detail?.id)?.evaluationFormCount ?? 0

const open = (detail: EditableDetail | null) => {
    const first = props.accessOptions.find((option) => option.formId === props.formId)
    editing.value =
        detail ??
        blankDetail(Number(first?.value ?? 0), Number(props.phaseTypeOptions[0]?.value ?? 0))
    dialogOpen.value = true
}
const store = (saved: EditableDetail) => {
    const index = details.value.findIndex((detail) => detail.key === saved.key)
    details.value =
        index === -1
            ? [...details.value, saved]
            : details.value.map((detail, i) => (i === index ? saved : detail))
}
const remove = (key: string) => (details.value = details.value.filter((d) => d.key !== key))
</script>

<template>
    <SectionCard title="Tahapan Form">
        <template #actions>
            <Button type="button" size="sm" @click="open(null)"
                ><Plus class="size-4" />Tambah Detail</Button
            >
        </template>
        <ul class="divide-y">
            <li
                v-for="(detail, index) in details"
                :key="detail.key"
                class="flex flex-wrap items-start justify-between gap-3 py-3"
            >
                <div class="flex min-w-0 items-start gap-2">
                    <span class="rounded border px-1.5 text-xs text-muted-foreground">{{
                        orderOf(detail)
                    }}</span>
                    <div class="min-w-0 space-y-0.5">
                        <p class="text-sm font-medium">{{ heading(detail).title }}</p>
                        <p class="text-xs text-muted-foreground">
                            Akses: {{ heading(detail).access }}
                        </p>
                        <p
                            v-for="line in deadlines(detail)"
                            :key="line"
                            class="text-xs text-muted-foreground"
                        >
                            {{ line }}
                        </p>
                        <p v-if="removeHint(detail)" class="text-xs text-muted-foreground">
                            {{ removeHint(detail) }}
                        </p>
                        <p
                            v-for="message in rowErrors(index)"
                            :key="message"
                            class="text-xs text-destructive"
                        >
                            {{ message }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <Badge :variant="detail.needsReview ? 'default' : 'outline'">{{
                        detail.needsReview ? 'Perlu Review' : 'Tanpa Review'
                    }}</Badge>
                    <Button type="button" variant="ghost" size="sm" @click="open(detail)"
                        ><Pencil class="size-4" />Edit</Button
                    >
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="removeHint(detail) !== undefined"
                        @click="remove(detail.key)"
                        ><Trash2 class="size-4" />Hapus</Button
                    >
                </div>
            </li>
        </ul>
        <p v-if="details.length === 0" class="text-sm text-muted-foreground">Belum ada detail.</p>
        <PhaseDetailDialog
            v-model:open="dialogOpen"
            :detail="editing"
            :evaluation-form-count="evaluationFormsOf(editing)"
            :access-options="accessOptions"
            :phase-type-options="phaseTypeOptions"
            :deadline-options="deadlineOptions"
            @save="store"
        />
    </SectionCard>
</template>
