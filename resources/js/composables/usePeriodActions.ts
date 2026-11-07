import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import type { PeriodRowData } from '@/types/form-engine'

// Tutup (Close Early, W68) dan Hapus (BR-FE-21, W70) satu periode. Kelayakan dibaca dari
// backend (`closable`, `deleteBlockers`), tidak dihitung ulang di sini.
export function usePeriodActions() {
    const target = ref<PeriodRowData | null>(null)
    const blockedOpen = ref(false)
    const deleteOpen = ref(false)
    const closeOpen = ref(false)
    const processing = ref(false)

    const askDelete = (row: PeriodRowData) => {
        target.value = row
        if (row.deleteBlockers.length > 0) blockedOpen.value = true
        else deleteOpen.value = true
    }

    const askClose = (row: PeriodRowData) => {
        target.value = row
        blockedOpen.value = false
        closeOpen.value = true
    }

    // BR-SCH-07 + BR-SM-10 — konsekuensinya disebut sebelum Operator memutuskan.
    const closeImpact = computed(() => {
        const owners = target.value?.draftOwnerCount ?? 0
        const drafts =
            owners > 0
                ? `${owners} peneliti dengan draft berjalan akan diberi tahu dan draftnya diarsipkan.`
                : 'Tidak ada draft berjalan.'

        return `Pengajuan baru tidak bisa dibuat dan semua tahap pengajuan periode ini terkunci, termasuk yang tenggatnya belum lewat. Siklus monev tetap mengikuti tenggatnya sendiri. ${drafts} Penutupan tidak bisa dibatalkan dari layar ini.`
    })

    const visit = (done: () => void) => ({
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onSuccess: done,
        onFinish: () => (processing.value = false),
    })

    const confirmDelete = () => {
        if (!target.value) return
        router.delete(
            route('admin.submission-periods.destroy', target.value.id),
            visit(() => (deleteOpen.value = false))
        )
    }

    const confirmClose = () => {
        if (!target.value) return
        router.post(
            route('admin.submission-periods.close', target.value.id),
            {},
            visit(() => (closeOpen.value = false))
        )
    }

    return {
        target,
        blockedOpen,
        deleteOpen,
        closeOpen,
        processing,
        closeImpact,
        askDelete,
        askClose,
        confirmDelete,
        confirmClose,
    }
}
