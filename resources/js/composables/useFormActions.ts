import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import type { FormRowData } from '@/types/form-engine'

// Aktifkan/nonaktifkan dan hapus satu Form (BR-FE-19, W76, W78). Kelayakan hapus dibaca dari
// backend lewat `submissionCount`; layar hanya memilih dialog yang sesuai.
export function useFormActions() {
    const target = ref<FormRowData | null>(null)
    const toggling = ref(false)
    const deleting = ref(false)
    const blocked = ref(false)
    const processing = ref(false)

    const requestToggle = (row: FormRowData) => {
        target.value = row
        blocked.value = false
        toggling.value = true
    }

    const requestDelete = (row: FormRowData) => {
        target.value = row
        if (row.submissionCount > 0) blocked.value = true
        else deleting.value = true
    }

    // W76 — menonaktifkan tidak menyentuh pengajuan yang sedang berjalan; angkanya disebut.
    const toggleCopy = computed(() => {
        const row = target.value
        if (!row) return { title: '', description: '', confirmLabel: '' }
        if (row.isActive) {
            return {
                title: 'Nonaktifkan form?',
                description: `${row.title} berhenti menerima pengajuan baru. ${row.activeSubmissionCount} pengajuan aktif tetap berjalan dan terbaca.`,
                confirmLabel: 'Nonaktifkan Form',
            }
        }

        return {
            title: 'Aktifkan form?',
            description: `${row.title} kembali menerima pengajuan baru selama kontrol akses dan periodenya terbuka.`,
            confirmLabel: 'Aktifkan Form',
        }
    })

    const visit = (done: () => void) => ({
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onSuccess: done,
        onFinish: () => (processing.value = false),
    })

    const confirmToggle = () => {
        if (!target.value) return
        router.patch(
            route('admin.forms.active', target.value.id),
            { active: !target.value.isActive },
            visit(() => (toggling.value = false))
        )
    }

    const confirmDelete = () => {
        if (!target.value) return
        router.delete(
            route('admin.forms.destroy', target.value.id),
            visit(() => (deleting.value = false))
        )
    }

    return {
        target,
        toggling,
        deleting,
        blocked,
        processing,
        toggleCopy,
        requestToggle,
        requestDelete,
        confirmToggle,
        confirmDelete,
    }
}
