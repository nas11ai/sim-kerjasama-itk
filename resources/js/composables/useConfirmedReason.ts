import { useForm } from '@inertiajs/vue3'
import { ref, type Ref } from 'vue'

// Aksi final Operator: alasan, konfirmasi kedua, lalu POST. Galat membiarkan dialog terbuka beserta isiannya.
export function useConfirmedReason(url: () => string, open: Ref<boolean>) {
    const form = useForm({ reason: '' })
    const confirming = ref(false)

    const send = () =>
        form.post(url(), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false
                form.reset()
            },
            onFinish: () => {
                confirming.value = false
            },
        })

    return { form, confirming, send }
}
