<script setup lang="ts">
import { computed } from 'vue'

import FileInput from '@/Components/app/FileInput.vue'
import { Button } from '@/Components/ui/button'
import { Label } from '@/Components/ui/label'
import type { AnnouncementFile } from '@/types/announcement'

const props = defineProps<{
    existing: AnnouncementFile[]
    errors: Partial<Record<string, string>>
}>()

const files = defineModel<File[]>('files', { required: true })
const deletedFiles = defineModel<number[]>('deletedFiles', { required: true })

// Galat `files` atau `files.N` dari AnnouncementRequest; cukup satu yang terbaca.
const fileError = computed(
    () =>
        props.errors.files ??
        Object.entries(props.errors).find(([key]) => key.startsWith('files.'))?.[1]
)

function toggleRemoved(id: number) {
    deletedFiles.value = deletedFiles.value.includes(id)
        ? deletedFiles.value.filter((removed) => removed !== id)
        : [...deletedFiles.value, id]
}

function pick(event: Event) {
    const input = event.target
    if (input instanceof HTMLInputElement) {
        files.value = Array.from(input.files ?? [])
    }
}
</script>

<template>
    <div class="space-y-2">
        <Label for="announcement-files">Lampiran</Label>
        <ul v-if="existing.length > 0" class="space-y-1">
            <li
                v-for="file in existing"
                :key="file.id"
                class="flex items-center justify-between gap-2 rounded-md border px-3 py-1.5 text-sm"
            >
                <a
                    :href="file.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="truncate hover:underline"
                    :class="{
                        'text-muted-foreground line-through': deletedFiles.includes(file.id),
                    }"
                >
                    {{ file.name }}
                </a>
                <Button variant="ghost" size="sm" type="button" @click="toggleRemoved(file.id)">
                    {{ deletedFiles.includes(file.id) ? 'Batalkan' : 'Lepas' }}
                </Button>
            </li>
        </ul>
        <FileInput
            id="announcement-files"
            accept=".jpg,.png,.pdf"
            multiple
            :aria-invalid="fileError !== undefined"
            aria-describedby="announcement-files-message"
            @change="pick"
        />
        <p v-if="fileError" id="announcement-files-message" class="text-xs text-destructive">
            {{ fileError }}
        </p>
        <p v-else id="announcement-files-message" class="text-xs text-muted-foreground">
            JPG, PNG, atau PDF, maks. 2 MB per berkas
        </p>
    </div>
</template>
