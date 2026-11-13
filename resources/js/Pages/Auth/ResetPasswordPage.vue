<script setup lang="ts">
// Frame: SHARED-01-09
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'

import AuthHeading from '@/Components/Auth/AuthHeading.vue'
import PasswordPairFields from '@/Components/Auth/PasswordPairFields.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import { Button } from '@/Components/ui/button'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import type { ResetPasswordPageProps } from '@/types/auth'

const props = defineProps<ResetPasswordPageProps>()

const form = useForm({
    token: props.token,
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
})

const submit = () =>
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
</script>

<template>
    <Head title="Reset Password" />
    <AuthLayout>
        <AuthHeading title="Reset Password">
            Buat password baru untuk akun <strong>{{ email ?? '-' }}</strong>
        </AuthHeading>

        <form class="space-y-4" @submit.prevent="submit">
            <PasswordPairFields
                v-model:password="form.password"
                v-model:confirmation="form.password_confirmation"
                label="Password Baru"
                confirmation-label="Konfirmasi Password Baru"
                hint="Minimal 8 karakter"
                :error="form.errors.password"
            />

            <!-- Token kedaluwarsa atau email tak dikenal dilaporkan Laravel di field email -->
            <AppBanner v-if="form.errors.email" tone="denial">{{ form.errors.email }}</AppBanner>

            <Button type="submit" class="w-full" :disabled="form.processing">
                <Check />
                Simpan Password Baru
            </Button>
        </form>

        <p class="mt-6 text-center text-sm">
            <Link :href="route('login')" class="underline">Kembali ke Login</Link>
        </p>
    </AuthLayout>
</template>
