<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Pencil, Printer } from 'lucide-vue-next'
import { computed } from 'vue'

import ActionPanel from '@/Components/app/ActionPanel.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import type { ActionItem } from '@/types/action'
import type { FilterOption, SubmissionAbilities, SubmissionDetail } from '@/types/submission-page'

const props = defineProps<{
    submission: SubmissionDetail
    can: SubmissionAbilities
    printableDocuments: FilterOption[]
}>()

// BR-SM-04 — Submit tidak ditawarkan di sini: ia tindakan ketua di langkah akhir wizard.
const editHint = computed(() => {
    if (props.submission.isArchived) {
        return 'Draft dari periode yang sudah ditutup, tidak dapat diedit lagi.'
    }
    return props.can.resubmit
        ? 'Tersedia saat status Perlu Revisi.'
        : 'Tersedia saat pengajuan masih berstatus Draft.'
})

const actions = computed<ActionItem[]>(() => [
    {
        key: 'edit',
        label: props.can.resubmit ? 'Mulai Revisi' : 'Edit Pengajuan',
        icon: Pencil,
        href: props.submission.editUrl ?? undefined,
        available: props.can.update && props.submission.editUrl !== null,
        hint: editHint.value,
    },
    // BR-RPT-11 — daftar dokumen sudah disaring backend, termasuk skor yang masih ditahan.
    ...props.printableDocuments.map((document) => ({
        key: `print-${document.value}`,
        label: `Cetak ${document.label}`,
        icon: Printer,
        onSelect: () =>
            router.post(
                route('submissions.print', [String(props.submission.id), document.value]),
                {},
                { preserveScroll: true }
            ),
        available: true,
        hint: 'Berkas cetak masuk ke daftar ekspor Anda.',
    })),
])
</script>

<template>
    <div class="flex flex-col gap-4">
        <ActionPanel :actions="actions" />
        <!-- BR-SM-11 — penarikan bersifat terminal, jalurnya lewat Operator -->
        <AppBanner v-if="!can.withdraw" tone="info">
            Untuk menarik pengajuan, hubungi Operator LPPM.
        </AppBanner>
    </div>
</template>
