<script setup lang="ts">
// Frame: SHARED-01-08
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Mail } from 'lucide-vue-next'

import AuthHeading from '@/Components/Auth/AuthHeading.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import type { ForgotPasswordPageProps } from '@/types/auth'

defineProps<ForgotPasswordPageProps>()

const form = useForm({ email: '' })

const submit = () => form.post(route('password.email'))
</script>

<template>
    <Head title="Lupa Password" />
    <AuthLayout>
        <AuthHeading
            title="Lupa Password"
            subtitle="Masukkan email akun Anda — kami kirimkan link reset password."
        />

        <form class="space-y-4" @submit.prevent="submit">
            <AppBanner v-if="status" tone="success">{{ status }}</AppBanner>

            <FormField id="email" label="Email" :error="form.errors.email" required>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    placeholder="nama@itk.ac.id"
                    autofocus
                    required
                />
            </FormField>

            <Button type="submit" class="w-full" :disabled="form.processing">
                <Mail />
                Kirim Link Reset
            </Button>
        </form>

        <p class="mt-6 text-center text-sm">
            <Link :href="route('login')" class="underline">Kembali ke Login</Link>
        </p>
    </AuthLayout>
</template>
