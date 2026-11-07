<script setup lang="ts">
import { X } from 'lucide-vue-next'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import { Button } from '@/Components/ui/button'
import { Label } from '@/Components/ui/label'
import { formatFileSize } from '@/lib/format'
import type { OutputFile } from '@/types/research-output'

const props = defineProps<{
    existing: OutputFile[]
    requiresFiles: boolean
    errors: Record<string, string>
}>()
const files = defineModel<File[]>({ required: true })

// BR-RO-04 — berkas lama ikut dihitung: menyunting HKI tidak menuntut unggah ulang.
const missing = computed(
    () => props.requiresFiles && props.existing.length + files.value.length === 0
)
const fileErrors = computed(() =>
    Object.entries(props.errors).flatMap(([key, message]) =>
        key === 'files' || key.startsWith('files.') ? [message] : []
    )
)

const add = (event: Event) => {
    if (!(event.target instanceof HTMLInputElement)) return
    files.value = [...files.value, ...Array.from(event.target.files ?? [])]
    event.target.value = ''
}
const remove = (index: number) => (files.value = files.value.filter((_, i) => i !== index))
</script>

<template>
    <div class="flex flex-col gap-2">
        <Label for="output-files">File Bukti</Label>
        <ul v-if="existing.length" class="flex flex-col gap-1 text-sm">
            <li v-for="file in existing" :key="file.id">
                <a :href="file.url" class="text-primary underline-offset-4 hover:underline">
                    {{ file.name }}
                </a>
                <span v-if="file.size !== null" class="text-muted-foreground">
                    · {{ formatFileSize(file.size) }}
                </span>
            </li>
        </ul>
        <input
            id="output-files"
            type="file"
            multiple
            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
            class="text-sm file:mr-3 file:rounded-md file:border file:bg-transparent file:px-3 file:py-1"
            aria-describedby="output-files-hint"
            @change="add"
        />
        <p id="output-files-hint" class="text-xs text-muted-foreground">
            PDF artikel / LoA. Wajib dilampirkan untuk luaran HKI dan Prototipe.
        </p>
        <ul v-if="files.length" class="flex flex-col gap-1 text-sm">
            <li
                v-for="(file, index) in files"
                :key="`${file.name}-${index}`"
                class="flex items-center gap-2"
            >
                {{ file.name }} · {{ formatFileSize(file.size) }}
                <Button
                    variant="ghost"
                    size="icon"
                    :aria-label="`Batalkan ${file.name}`"
                    @click="remove(index)"
                >
                    <X class="size-4" />
                </Button>
            </li>
        </ul>
        <p v-for="message in fileErrors" :key="message" class="text-xs text-destructive">
            {{ message }}
        </p>
        <AppBanner v-if="missing" tone="warning">
            Luaran HKI dan Prototipe wajib punya minimal satu berkas bukti.
        </AppBanner>
    </div>
</template>
