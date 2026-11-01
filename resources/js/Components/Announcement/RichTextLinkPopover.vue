<script setup lang="ts">
import type { Editor } from '@tiptap/vue-3'
import { Link2 } from 'lucide-vue-next'
import { ref } from 'vue'

import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'
import { isAllowedLink } from '@/lib/richText'

// Pengganti prompt() native (§2): URL diketik di popover, bukan dialog browser.
const props = defineProps<{ editor: Editor }>()

const open = ref(false)
const href = ref('')
const error = ref<string | null>(null)

function changeOpen(value: boolean) {
    open.value = value
    if (value) {
        href.value = props.editor.getAttributes('link').href ?? ''
        error.value = null
    }
}

function apply() {
    const url = href.value.trim()
    if (url === '') {
        remove()
        return
    }
    if (!isAllowedLink(url)) {
        error.value = 'Tautan harus diawali http://, https://, atau mailto:.'
        return
    }
    props.editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
    open.value = false
}

function remove() {
    props.editor.chain().focus().extendMarkRange('link').unsetLink().run()
    open.value = false
}
</script>

<template>
    <Popover :open="open" @update:open="changeOpen">
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-8 aria-pressed:bg-accent"
                aria-label="Tautan"
                title="Tautan"
                :aria-pressed="editor.isActive('link')"
            >
                <Link2 class="size-4" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-80 space-y-2">
            <form class="space-y-2" @submit.prevent="apply">
                <Label for="rich-text-link">Alamat tautan</Label>
                <Input
                    id="rich-text-link"
                    v-model="href"
                    type="url"
                    placeholder="https://"
                    :aria-invalid="error !== null"
                />
                <p v-if="error" class="text-xs text-destructive">{{ error }}</p>
                <div class="flex justify-end gap-2">
                    <Button
                        v-if="editor.isActive('link')"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="remove"
                    >
                        Hapus tautan
                    </Button>
                    <Button type="submit" size="sm">Terapkan</Button>
                </div>
            </form>
        </PopoverContent>
    </Popover>
</template>
