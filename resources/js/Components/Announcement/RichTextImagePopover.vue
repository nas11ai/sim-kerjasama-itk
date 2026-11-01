<script setup lang="ts">
import type { Editor } from '@tiptap/vue-3'
import { ImagePlus } from 'lucide-vue-next'
import { ref } from 'vue'

import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'
import { useInlineImageUpload } from '@/composables/useInlineImageUpload'

// fe-v2-design §4.7 K6a — gambar hanya lewat unggahan; tidak ada kolom URL gambar.
const props = defineProps<{ editor: Editor; uploadUrl: string }>()

const { upload, uploading, error } = useInlineImageUpload(props.uploadUrl)
const open = ref(false)
const file = ref<File | null>(null)
const alt = ref('')

function pick(event: Event) {
    const input = event.target
    if (input instanceof HTMLInputElement) {
        file.value = input.files?.[0] ?? null
    }
}

async function insert() {
    if (file.value === null) {
        return
    }
    const src = await upload(file.value)
    if (src === null) {
        return
    }
    props.editor.chain().focus().setImage({ src, alt: alt.value.trim() }).run()
    file.value = null
    alt.value = ''
    open.value = false
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-8"
                aria-label="Gambar"
                title="Gambar"
            >
                <ImagePlus class="size-4" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-80">
            <form class="space-y-2" @submit.prevent="insert">
                <Label for="rich-text-image">Berkas gambar</Label>
                <Input
                    id="rich-text-image"
                    type="file"
                    accept="image/jpeg,image/png"
                    :aria-invalid="error !== undefined"
                    @change="pick"
                />
                <p v-if="error" class="text-xs text-destructive">{{ error }}</p>
                <p v-else class="text-xs text-muted-foreground">
                    JPG atau PNG, maks. 2 MB dan 4096 px per sisi.
                </p>
                <Label for="rich-text-image-alt">Teks alternatif</Label>
                <Input
                    id="rich-text-image-alt"
                    v-model="alt"
                    maxlength="255"
                    placeholder="Gambaran singkat isi gambar"
                />
                <div class="flex justify-end">
                    <Button type="submit" size="sm" :disabled="file === null || uploading">
                        {{ uploading ? 'Mengunggah...' : 'Sisipkan' }}
                    </Button>
                </div>
            </form>
        </PopoverContent>
    </Popover>
</template>
