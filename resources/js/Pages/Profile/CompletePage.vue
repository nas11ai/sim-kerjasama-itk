<script setup lang="ts">
// Frame: SHARED-01-06
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'
import { computed } from 'vue'

import FileInput from '@/Components/app/FileInput.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import DetailsFields from '@/Components/Profile/DetailsFields.vue'
import { Button } from '@/Components/ui/button'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { ProfileCompletePageProps } from '@/types/profile'

const props = defineProps<ProfileCompletePageProps>()

const form = useForm<{ phone: string; expertise: string; photo: File | null }>({
    phone: props.profile.phone ?? '',
    expertise: props.profile.expertise ?? '',
    photo: null,
})

const registration = computed(() => [
    { label: 'Nama', value: props.profile.name },
    { label: 'NIDN', value: props.profile.nidn ?? '-' },
    { label: 'Organisasi', value: props.profile.organization ?? '-' },
])
const missingCount = computed(() => props.profile.missing.length)
const hasPhoto = computed(() => props.profile.photoUrl !== null)

const pickPhoto = (event: Event) => {
    const input = event.target

    if (input instanceof HTMLInputElement) form.photo = input.files?.[0] ?? null
}

const save = () =>
    form.post(route('profile.complete.store', props.next ? { next: props.next } : undefined), {
        forceFormData: true,
    })
</script>

<template>
    <Head title="Lengkapi Profil" />
    <AppLayout>
        <PageHeader
            title="Lengkapi Profil"
            subtitle="Lengkapi profil Anda terlebih dahulu untuk dapat membuat pengajuan."
        />
        <div class="flex max-w-2xl flex-col gap-6">
            <!-- Paket 2 P1 — pengalihan dari Buat Pengajuan Baru menjelaskan dirinya (BR-IAM-03) -->
            <AppBanner
                v-if="next === 'submission'"
                tone="lock"
                title="Pengajuan baru menunggu profil lengkap"
            >
                Anda diarahkan ke sini dari Buat Pengajuan Baru. Pengajuan baru bisa dibuat setelah
                nomor telepon, bidang keahlian, dan foto profil terisi. Setelah profil disimpan,
                Anda langsung dibawa kembali ke langkah pertama pengajuan.
            </AppBanner>
            <AppBanner tone="info">
                Data dasar berasal dari registrasi dan tidak dapat diubah di halaman ini.
            </AppBanner>
            <!-- BR-IAM-03 — ketiganya wajib; terisi sebagian tetap belum lengkap (IAM-VERIFY-12) -->
            <AppBanner v-if="missingCount > 0 && missingCount < 3" tone="warning">
                {{ missingCount }} dari 3 data tambahan belum terisi. Profil dihitung lengkap hanya
                kalau ketiganya ada.
            </AppBanner>
            <SectionCard title="Data Registrasi">
                <InfoGrid :items="registration" />
            </SectionCard>
            <form class="flex flex-col gap-6" @submit.prevent="save">
                <SectionCard title="Data Tambahan">
                    <DetailsFields
                        v-model:phone="form.phone"
                        v-model:expertise="form.expertise"
                        :errors="form.errors"
                    />
                    <FormField
                        id="profile-photo"
                        label="Foto Profil"
                        :required="!hasPhoto"
                        :hint="
                            hasPhoto
                                ? 'Foto tersimpan. Unggah lagi untuk mengganti.'
                                : 'JPG/PNG maks 2MB.'
                        "
                        :error="form.errors.photo"
                    >
                        <template #default="{ describedBy }">
                            <FileInput
                                id="profile-photo"
                                accept="image/jpeg,image/png"
                                :aria-describedby="describedBy"
                                @change="pickPhoto"
                            />
                        </template>
                    </FormField>
                </SectionCard>
                <div class="flex flex-wrap gap-2">
                    <Button type="submit" :disabled="form.processing">
                        <Check class="size-4" /> Simpan Profil
                    </Button>
                    <Button variant="ghost" as-child>
                        <Link :href="route('dashboard')">Nanti Saja</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
