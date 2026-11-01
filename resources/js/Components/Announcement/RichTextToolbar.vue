<script setup lang="ts">
import type { Editor } from '@tiptap/vue-3'
import {
    Bold,
    Heading2,
    Heading3,
    Italic,
    List,
    ListOrdered,
    type LucideIcon,
    Quote,
    Redo2,
    Strikethrough,
    Underline,
    Undo2,
} from 'lucide-vue-next'

import RichTextImagePopover from '@/Components/Announcement/RichTextImagePopover.vue'
import RichTextLinkPopover from '@/Components/Announcement/RichTextLinkPopover.vue'
import { Button } from '@/Components/ui/button'

const props = defineProps<{ editor: Editor; imageUploadUrl: string }>()

interface Tool {
    label: string
    icon: LucideIcon
    active?: () => boolean
    run: () => void
}

const chain = () => props.editor.chain().focus()

const formatting: Tool[] = [
    {
        label: 'Tebal',
        icon: Bold,
        active: () => props.editor.isActive('bold'),
        run: () => chain().toggleBold().run(),
    },
    {
        label: 'Miring',
        icon: Italic,
        active: () => props.editor.isActive('italic'),
        run: () => chain().toggleItalic().run(),
    },
    {
        label: 'Garis bawah',
        icon: Underline,
        active: () => props.editor.isActive('underline'),
        run: () => chain().toggleUnderline().run(),
    },
    {
        label: 'Coret',
        icon: Strikethrough,
        active: () => props.editor.isActive('strike'),
        run: () => chain().toggleStrike().run(),
    },
    {
        label: 'Judul 2',
        icon: Heading2,
        active: () => props.editor.isActive('heading', { level: 2 }),
        run: () => chain().toggleHeading({ level: 2 }).run(),
    },
    {
        label: 'Judul 3',
        icon: Heading3,
        active: () => props.editor.isActive('heading', { level: 3 }),
        run: () => chain().toggleHeading({ level: 3 }).run(),
    },
    {
        label: 'Daftar berbutir',
        icon: List,
        active: () => props.editor.isActive('bulletList'),
        run: () => chain().toggleBulletList().run(),
    },
    {
        label: 'Daftar bernomor',
        icon: ListOrdered,
        active: () => props.editor.isActive('orderedList'),
        run: () => chain().toggleOrderedList().run(),
    },
    {
        label: 'Kutipan',
        icon: Quote,
        active: () => props.editor.isActive('blockquote'),
        run: () => chain().toggleBlockquote().run(),
    },
]

const history: Tool[] = [
    { label: 'Urungkan', icon: Undo2, run: () => chain().undo().run() },
    { label: 'Ulangi', icon: Redo2, run: () => chain().redo().run() },
]
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-0.5 border-b p-1"
        role="toolbar"
        aria-label="Format isi"
    >
        <Button
            v-for="tool in formatting"
            :key="tool.label"
            type="button"
            variant="ghost"
            size="icon"
            class="size-8 aria-pressed:bg-accent"
            :aria-label="tool.label"
            :title="tool.label"
            :aria-pressed="tool.active?.() ?? false"
            @click="tool.run"
        >
            <component :is="tool.icon" class="size-4" />
        </Button>
        <RichTextLinkPopover :editor="editor" />
        <RichTextImagePopover :editor="editor" :upload-url="imageUploadUrl" />
        <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />
        <Button
            v-for="tool in history"
            :key="tool.label"
            type="button"
            variant="ghost"
            size="icon"
            class="size-8"
            :aria-label="tool.label"
            :title="tool.label"
            @click="tool.run"
        >
            <component :is="tool.icon" class="size-4" />
        </Button>
    </div>
</template>
