<script setup lang="ts">
// Frame: SHARED-01-01
import { Head, Link, useForm } from '@inertiajs/vue3'

import PublicAnnouncementPanel from '@/Components/Announcement/PublicAnnouncementPanel.vue'
import AuthHeading from '@/Components/Auth/AuthHeading.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import { Button } from '@/Components/ui/button'
import { Checkbox } from '@/Components/ui/checkbox'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import type { LoginPageProps } from '@/types/auth'

defineProps<LoginPageProps>()

const form = useForm({ email: '', password: '', remember: false })

const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') })
</script>

<template>
    <Head title="Masuk" />
    <AuthLayout>
        <AuthHeading
            title="Masuk ke SIMPAS"
            subtitle="Sistem Informasi Penelitian dan Pengabdian: LPPM ITK"
        />

        <form class="space-y-4" @submit.prevent="submit">
            <!-- W170 — tab yang dikeluarkan lewat sessions.ended mendarat di sini -->
            <AppBanner v-if="notice" tone="denial" data-testid="session-notice">{{
                notice
            }}</AppBanner>
            <AppBanner v-if="status" tone="success">{{ status }}</AppBanner>

            <FormField id="email" label="Email" required>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    placeholder="nama@itk.ac.id"
                    autofocus
                    required
                />
            </FormField>
            <FormField id="password" label="Password" required>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                />
            </FormField>

            <div class="flex items-center justify-between text-sm">
                <Label class="flex items-center gap-2 font-normal">
                    <Checkbox
                        :model-value="form.remember"
                        @update:model-value="(checked) => (form.remember = checked === true)"
                    />
                    Ingat saya
                </Label>
                <Link v-if="canResetPassword" :href="route('password.request')" class="underline">
                    Lupa password?
                </Link>
            </div>

            <!-- BR-IAM-05 — keaktifan akun menggerbang login; kredensial salah memakai banner yang sama -->
            <AppBanner v-if="form.errors.email" tone="denial">{{ form.errors.email }}</AppBanner>

            <Button type="submit" class="w-full" :disabled="form.processing">Masuk</Button>
        </form>

        <p class="mt-6 text-center text-sm text-muted-foreground">
            Belum punya akun?
            <Link :href="route('register')" class="text-foreground underline">
                Daftar sebagai Dosen ITK
            </Link>
        </p>
        <template v-if="announcements.length > 0" #aside>
            <PublicAnnouncementPanel :announcements="announcements" />
        </template>
    </AuthLayout>
</template>
