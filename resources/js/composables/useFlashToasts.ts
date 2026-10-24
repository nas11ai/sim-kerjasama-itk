import { router, usePage } from '@inertiajs/vue3'
import { onScopeDispose } from 'vue'

import { toast } from '@/Components/ui/toast'
import type { PageProps } from '@/types'

// Dipanggil sekali di AppLayout. workloadWarnings milik layar penugasan, bukan di sini.
// 'finish' hanya muncul untuk request ke server; Back/Forward dipulihkan tanpa request.
export function useFlashToasts(): void {
    const page = usePage<PageProps>()

    const showFlash = () => {
        const flash = page.props.flash
        if (flash.success) toast({ title: flash.success })
        if (flash.error) toast({ title: flash.error, variant: 'destructive' })
        if (flash.message) toast({ title: flash.message })

        // Dikosongkan supaya partial reload tidak memutar ulang dan flash identik berikutnya tidak disamakan.
        flash.success = flash.error = flash.message = null
    }

    showFlash()
    onScopeDispose(router.on('finish', showFlash))
}
