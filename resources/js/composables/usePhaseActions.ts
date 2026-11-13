import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import type { PhaseCardData } from '@/types/form-engine'

// Aktifkan/nonaktifkan dan hapus satu tahap dari pustaka fase (BR-FE-20, W101). Kelayakan hapus
// dibaca dari backend lewat `submissionCount`; layar hanya memilih dialog yang sesuai.
export function usePhaseActions() {
    const target = ref<PhaseCardData | null>(null)
    const toggling = ref(false)
    const deleting = ref(false)
    const blocked = ref(false)
    const processing = ref(false)

    const requestToggle = (phase: PhaseCardData) => {
        target.value = phase
        blocked.value = false
        toggling.value = true
    }

    const requestDelete = (phase: PhaseCardData) => {
        target.value = phase
        if (phase.submissionCount > 0) blocked.value = true
        else deleting.value = true
    }

    const toggleCopy = computed(() => {
        const phase = target.value
        if (!phase) return { title: '', description: '', confirmLabel: '' }
        if (phase.isActive) {
            return {
                title: 'Nonaktifkan tahap?',
                description: `${phase.title} berhenti menerima pengajuan baru. Pengajuan yang sudah ada tetap berjalan dan terbaca.`,
                confirmLabel: 'Nonaktifkan Tahap',
            }
        }

        return {
            title: 'Aktifkan tahap?',
            description: `${phase.title} kembali menerima pengajuan baru selama periodenya terbuka.`,
            confirmLabel: 'Aktifkan Tahap',
        }
    })

    const deleteDescription = computed(
        () =>
            `${target.value?.title ?? ''} beserta ${target.value?.detailCount ?? 0} detail dan formulir evaluasinya dihapus permanen.`
    )

    const visit = (done: () => void) => ({
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onSuccess: done,
        onFinish: () => (processing.value = false),
    })

    const confirmToggle = () => {
        if (!target.value) return
        router.patch(
            route('admin.phases.active', target.value.id),
            { active: !target.value.isActive },
            visit(() => (toggling.value = false))
        )
    }

    const confirmDelete = () => {
        if (!target.value) return
        router.delete(
            route('admin.phases.destroy', target.value.id),
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
        deleteDescription,
        requestToggle,
        requestDelete,
        confirmToggle,
        confirmDelete,
    }
}
