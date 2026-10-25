<script setup lang="ts">
// Frame: — (tidak ada di wireframe; route `verified` menjaga dashboard, lihat W3)
import { Head, Link, useForm } from '@inertiajs/vue3'

import AuthHeading from '@/Components/Auth/AuthHeading.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import { Button } from '@/Components/ui/button'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import type { VerifyEmailPageProps } from '@/types/auth'

defineProps<VerifyEmailPageProps>()

const form = useForm({})

const resend = () => form.post(route('verification.send'))
</script>

<template>
    <Head title="Verifikasi Email" />
    <AuthLayout>
        <AuthHeading
            title="Verifikasi Email"
            subtitle="Kami sudah mengirim tautan verifikasi ke email Anda. Buka tautan itu untuk melanjutkan."
        />

        <div class="space-y-4">
            <AppBanner v-if="status === 'verification-link-sent'" tone="success">
                Tautan verifikasi baru sudah dikirim.
            </AppBanner>

            <Button class="w-full" :disabled="form.processing" @click="resend">Kirim Ulang</Button>
            <Button as-child variant="outline" class="w-full">
                <Link :href="route('logout')" method="post" as="button">Keluar</Link>
            </Button>
        </div>
    </AuthLayout>
</template>
