import { useForm } from '@inertiajs/vue3'
import { computed, ref, type Ref } from 'vue'

// Aksi final Operator: alasan, konfirmasi kedua, lalu POST. Galat membiarkan dialog terbuka beserta isiannya.
export function useConfirmedReason(url: () => string, open: Ref<boolean>) {
    const form = useForm({ reason: '' })
    const confirming = ref(false)
    // Galat di luar field alasan (mis. `status` dari Action) tampil sebagai banner.
    const denials = computed(() =>
        Object.entries(form.errors)
            .filter(([key]) => key !== 'reason')
            .map(([, message]) => message)
            .filter((message): message is string => typeof message === 'string')
    )

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

    return { form, confirming, denials, send }
}
