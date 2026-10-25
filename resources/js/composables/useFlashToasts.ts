import { router } from '@inertiajs/vue3'
import { onScopeDispose } from 'vue'

import { toast } from '@/Components/ui/toast'

// Dipanggil sekali di AppLayout. workloadWarnings milik layar penugasan, bukan di sini.
// Event 'flash' muncul untuk halaman awal dan respons server, tidak untuk pemulihan
// riwayat: flash native tidak disimpan di history, jadi Back tidak memutarnya ulang.
export function useFlashToasts(): void {
    onScopeDispose(
        router.on('flash', (event) => {
            const { success, error, message } = event.detail.flash
            if (success) toast({ title: success })
            if (error) toast({ title: error, variant: 'destructive' })
            if (message) toast({ title: message })
        })
    )
}
