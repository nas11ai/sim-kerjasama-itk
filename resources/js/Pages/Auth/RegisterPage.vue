<script setup lang="ts">
// Frame: SHARED-01-02
import { Head, Link, useForm } from '@inertiajs/vue3'

import AuthHeading from '@/Components/Auth/AuthHeading.vue'
import OrganizationSelect from '@/Components/Auth/OrganizationSelect.vue'
import PasswordPairFields from '@/Components/Auth/PasswordPairFields.vue'
import FormField from '@/Components/app/FormField.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import type { RegisterPageProps } from '@/types/auth'

defineProps<RegisterPageProps>()

const form = useForm({
    name: '',
    email: '',
    nidn: '',
    organization_id: null as number | null,
    password: '',
    password_confirmation: '',
})

const submit = () =>
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
</script>

<template>
    <Head title="Daftar" />
    <AuthLayout>
        <AuthHeading
            title="Daftar — Dosen ITK"
            subtitle="Registrasi mandiri untuk dosen dengan email kampus"
        />

        <form class="space-y-4" @submit.prevent="submit">
            <FormField id="name" label="Nama Lengkap" :error="form.errors.name" required>
                <Input id="name" v-model="form.name" autocomplete="name" autofocus required />
            </FormField>

            <!-- BR-IAM-01 — domain persis @itk.ac.id, diperiksa backend; frontend tidak menebak -->
            <FormField
                id="email"
                label="Email ITK"
                hint="Harus berdomain @itk.ac.id persis — subdomain seperti @student.itk.ac.id tidak diterima."
                :error="form.errors.email"
                required
            >
                <template #default="{ describedBy }">
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        placeholder="nama@itk.ac.id"
                        :aria-describedby="describedBy"
                        required
                    />
                </template>
            </FormField>

            <!-- BR-IAM-02 — NIDN unik di seluruh sistem -->
            <FormField
                id="nidn"
                label="NIDN"
                hint="NIDN yang sudah terdaftar tidak dapat digunakan lagi."
                :error="form.errors.nidn"
                required
            >
                <template #default="{ describedBy }">
                    <Input
                        id="nidn"
                        v-model="form.nidn"
                        inputmode="numeric"
                        :aria-describedby="describedBy"
                        required
                    />
                </template>
            </FormField>

            <FormField
                id="organization_id"
                label="Unit Organisasi"
                :error="form.errors.organization_id"
                required
            >
                <template #default="{ describedBy }">
                    <OrganizationSelect
                        id="organization_id"
                        v-model="form.organization_id"
                        :faculties="faculties"
                        :described-by="describedBy"
                    />
                </template>
            </FormField>

            <PasswordPairFields
                v-model:password="form.password"
                v-model:confirmation="form.password_confirmation"
                :error="form.errors.password"
            />

            <Button type="submit" class="w-full" :disabled="form.processing">Daftar</Button>
        </form>

        <p class="mt-6 text-center text-sm text-muted-foreground">
            Sudah punya akun?
            <Link :href="route('login')" class="text-foreground underline">Masuk</Link>
        </p>
    </AuthLayout>
</template>
