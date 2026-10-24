import { usePage } from '@inertiajs/vue3'
import { watch } from 'vue'

import { toast } from '@/Components/ui/toast'
import type { PageProps } from '@/types'

// Dipanggil sekali di AppLayout. workloadWarnings milik layar penugasan, bukan di sini.
export function useFlashToasts(): void {
    const page = usePage<PageProps>()

    watch(
        () => page.props.flash,
        (flash) => {
            if (flash.success) toast({ title: flash.success })
            if (flash.error) toast({ title: flash.error, variant: 'destructive' })
            if (flash.message) toast({ title: flash.message })
        },
        { immediate: true }
    )
}
