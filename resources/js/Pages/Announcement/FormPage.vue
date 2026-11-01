<script setup lang="ts">
// Frame: — (fe-v2-design §4.7)
import { Head, useForm } from '@inertiajs/vue3'

import AttachmentFields from '@/Components/Announcement/AttachmentFields.vue'
import AudienceFields from '@/Components/Announcement/AudienceFields.vue'
import RichTextEditor from '@/Components/Announcement/RichTextEditor.vue'
import FormField from '@/Components/app/FormField.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { AnnouncementFormPageProps } from '@/types/announcement'

const props = defineProps<AnnouncementFormPageProps>()

const title = props.announcement === null ? 'Buat Pengumuman' : 'Sunting Pengumuman'
const newFiles: File[] = []
const deletedFiles: number[] = []

const form = useForm({
    title: props.announcement?.title ?? '',
    content: props.announcement?.content ?? '',
    type: props.announcement?.type ?? 'public',
    target_roles: props.announcement?.targetRoles ?? [],
    expired_at: props.announcement?.expiredOn ?? '',
    files: newFiles,
    deleted_files: deletedFiles,
})

function submit() {
    if (props.announcement === null) {
        form.post(route('admin.announcements.store'))
        return
    }
    // PHP tidak membaca body multipart pada PUT; method dititipkan lewat _method.
    form.transform((data) => ({ ...data, _method: 'put' })).post(
        route('admin.announcements.update', props.announcement.id)
    )
}
</script>

<template>
    <Head :title="title" />
    <AppLayout>
        <PageHeader
            :title="title"
            :back-href="route('admin.announcements.index')"
            back-label="Kelola Pengumuman"
        />
        <form class="max-w-3xl space-y-6" @submit.prevent="submit">
            <SectionCard title="Isi">
                <div class="space-y-4">
                    <FormField
                        id="announcement-title"
                        label="Judul"
                        required
                        :error="form.errors.title"
                    >
                        <Input
                            id="announcement-title"
                            v-model="form.title"
                            maxlength="255"
                            :aria-invalid="form.errors.title !== undefined"
                        />
                    </FormField>
                    <FormField
                        id="announcement-content"
                        label="Isi"
                        required
                        :error="form.errors.content"
                    >
                        <RichTextEditor
                            id="announcement-content"
                            v-model="form.content"
                            :invalid="form.errors.content !== undefined"
                            :image-upload-url="route('admin.announcements.images.store')"
                        />
                    </FormField>
                    <AttachmentFields
                        v-model:files="form.files"
                        v-model:deleted-files="form.deleted_files"
                        :existing="announcement?.files ?? []"
                        :errors="form.errors"
                    />
                </div>
            </SectionCard>
            <SectionCard title="Sasaran dan masa berlaku">
                <div class="space-y-4">
                    <AudienceFields
                        v-model:type="form.type"
                        v-model:target-roles="form.target_roles"
                        :role-options="roleOptions"
                        :errors="form.errors"
                    />
                    <FormField
                        id="announcement-expired-at"
                        label="Berlaku sampai"
                        hint="Kosongkan bila tanpa batas. Berlaku sampai pukul 23:59."
                        :error="form.errors.expired_at"
                    >
                        <Input
                            id="announcement-expired-at"
                            v-model="form.expired_at"
                            type="date"
                            class="sm:max-w-xs"
                        />
                    </FormField>
                </div>
            </SectionCard>
            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    {{ announcement === null ? 'Terbitkan' : 'Simpan Perubahan' }}
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
