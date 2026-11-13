<script setup lang="ts">
// Frame: SHARED-01-03
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

import AuthHeading from '@/Components/Auth/AuthHeading.vue'
import PasswordPairFields from '@/Components/Auth/PasswordPairFields.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import type { RegisterInvitationPageProps } from '@/types/auth'

const props = defineProps<RegisterInvitationPageProps>()

// Belum ada peta label permission (W4); nama mentahnya tetap lebih jujur daripada kosong.
const invitation = computed(() => [
    { label: 'Organisasi', value: props.organization ?? '-' },
    { label: 'Hak Akses', value: props.permissions.join(', ') || '-' },
])

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' })

const submit = () =>
    form.post(route('register.invitation', props.token), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
</script>

<template>
    <Head title="Daftar via Undangan" />
    <AuthLayout>
        <AuthHeading
            title="Daftar via Undangan"
            subtitle="Registrasi untuk peneliti eksternal / mitra"
        />

        <div class="mb-4 space-y-3">
            <AppBanner v-if="organization" tone="info">
                Anda diundang bergabung di <strong>{{ organization }}</strong
                >.
            </AppBanner>
            <InfoGrid :items="invitation" />
        </div>

        <!-- BR-IAM-06, BR-IAM-12 — jalur undangan: email bebas domain, tanpa NIDN -->
        <form class="space-y-4" @submit.prevent="submit">
            <FormField id="name" label="Nama Lengkap" :error="form.errors.name" required>
                <Input id="name" v-model="form.name" autocomplete="name" autofocus required />
            </FormField>
            <FormField
                id="email"
                label="Email"
                hint="Email bebas, tidak harus @itk.ac.id"
                :error="form.errors.email"
                required
            >
                <template #default="{ describedBy }">
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        placeholder="nama@contoh.com"
                        :aria-describedby="describedBy"
                        required
                    />
                </template>
            </FormField>

            <PasswordPairFields
                v-model:password="form.password"
                v-model:confirmation="form.password_confirmation"
                :error="form.errors.password"
            />

            <Button type="submit" class="w-full" :disabled="form.processing">Daftar & Masuk</Button>
        </form>

        <p class="mt-6 text-center text-sm text-muted-foreground">
            Akun Anda langsung aktif setelah mendaftar, tanpa perlu verifikasi NIDN.
        </p>
    </AuthLayout>
</template>
