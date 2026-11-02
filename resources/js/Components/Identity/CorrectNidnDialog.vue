<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

import ActionDialog from '@/Components/app/ActionDialog.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import { Input } from '@/Components/ui/input'

// BR-IAM-13 (Jalur 1) — koreksi bukan verifikasi; status tetap Ditolak sampai diverifikasi (W52).
const props = defineProps<{ profileId: number; currentNidn: string | null }>()
const open = defineModel<boolean>('open', { required: true })

const form = useForm({ nidn: props.currentNidn ?? '' })

const send = () =>
    form.post(route('admin.verifications.nidn', props.profileId), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    })
</script>

<template>
    <ActionDialog
        v-model:open="open"
        title="Koreksi NIDN"
        submit-label="Simpan Koreksi"
        :processing="form.processing"
        :submit-disabled="form.nidn.trim() === ''"
        @submit="send"
    >
        <FormField id="correct-nidn" label="NIDN" required :error="form.errors.nidn">
            <template #default="{ describedBy }">
                <Input
                    id="correct-nidn"
                    v-model="form.nidn"
                    inputmode="numeric"
                    :aria-describedby="describedBy"
                    :aria-invalid="form.errors.nidn ? true : undefined"
                />
            </template>
        </FormField>
        <AppBanner tone="info">
            Koreksi tidak memverifikasi akun. Setelah disimpan, tekan Verifikasi bila NIDN sudah
            cocok.
        </AppBanner>
    </ActionDialog>
</template>
