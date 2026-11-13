<script setup lang="ts">
// Frame: SHARED-01-07
import { Head, useForm } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'

import FormField from '@/Components/app/FormField.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import DetailsFields from '@/Components/Profile/DetailsFields.vue'
import PasswordCard from '@/Components/Profile/PasswordCard.vue'
import PhotoCard from '@/Components/Profile/PhotoCard.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { ProfileShowPageProps } from '@/types/profile'

const props = defineProps<ProfileShowPageProps>()

const form = useForm({
    name: props.profile.name,
    phone: props.profile.phone ?? '',
    expertise: props.profile.expertise ?? '',
})

const save = () => form.patch(route('profile.update'), { preserveScroll: true })
</script>

<template>
    <Head title="Profil Saya" />
    <AppLayout>
        <PageHeader title="Profil Saya" subtitle="Kelola data profil dan keamanan akun Anda." />
        <div class="flex flex-col gap-6">
            <div class="grid gap-6 lg:grid-cols-2">
                <PhotoCard v-if="profile.hasProfile" :profile="profile" />
                <SectionCard title="Data Profil">
                    <form
                        aria-label="Data Profil"
                        class="flex flex-col gap-4"
                        @submit.prevent="save"
                    >
                        <FormField
                            id="profile-name"
                            label="Nama Lengkap"
                            required
                            :error="form.errors.name"
                        >
                            <template #default="{ describedBy }">
                                <Input
                                    id="profile-name"
                                    v-model="form.name"
                                    autocomplete="name"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>
                        <!-- BR-IAM-01 — email tidak diubah sendiri (pemeriksaan domain hanya saat registrasi) -->
                        <FormField
                            id="profile-email"
                            label="Email"
                            hint="Tidak dapat diubah sendiri"
                        >
                            <Input id="profile-email" :model-value="profile.email" disabled />
                        </FormField>
                        <!-- BR-IAM-02/13 — NIDN hanya dikoreksi Operator (W52) -->
                        <FormField
                            v-if="profile.hasProfile"
                            id="profile-nidn"
                            label="NIDN"
                            hint="Tidak dapat diubah sendiri"
                        >
                            <Input id="profile-nidn" :model-value="profile.nidn ?? '-'" disabled />
                        </FormField>
                        <DetailsFields
                            v-if="profile.hasProfile"
                            v-model:phone="form.phone"
                            v-model:expertise="form.expertise"
                            :errors="form.errors"
                        />
                        <Button type="submit" class="self-start" :disabled="form.processing">
                            <Check class="size-4" /> Simpan Perubahan
                        </Button>
                    </form>
                </SectionCard>
            </div>
            <PasswordCard />
        </div>
    </AppLayout>
</template>
