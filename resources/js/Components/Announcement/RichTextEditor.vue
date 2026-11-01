<script setup lang="ts">
import Image from '@tiptap/extension-image'
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { onBeforeUnmount, watch } from 'vue'

import RichTextToolbar from '@/Components/Announcement/RichTextToolbar.vue'
import { INLINE_IMAGE_PREFIX, isAllowedLink } from '@/lib/richText'

// fe-v2-design §4.7 K6/K6a — wiring presentasi Tiptap; perilakunya dicek di browser
// karena ProseMirror tidak berjalan andal di jsdom. Server tetap batas kepercayaan.
const props = defineProps<{ id: string; invalid?: boolean; imageUploadUrl: string }>()
const model = defineModel<string>({ required: true })

// Hanya gambar sisipan milik sendiri yang dikenali: gambar luar yang ditempel dibuang
// di editor, sama seperti saringan server.
const InlineImage = Image.extend({
    parseHTML: () => [{ tag: `img[src^="${INLINE_IMAGE_PREFIX}"]` }],
}).configure({ inline: false, allowBase64: false })

const editor = useEditor({
    content: model.value,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            code: false,
            codeBlock: false,
            horizontalRule: false,
            link: {
                openOnClick: false,
                defaultProtocol: 'https',
                isAllowedUri: (url, { defaultValidate }) =>
                    defaultValidate(url) && isAllowedLink(url),
            },
        }),
        InlineImage,
    ],
    editorProps: {
        attributes: {
            id: props.id,
            role: 'textbox',
            'aria-multiline': 'true',
            'aria-label': 'Isi pengumuman',
            'aria-invalid': props.invalid ? 'true' : 'false',
            class: 'announcement-content min-h-48 px-3 py-2 text-sm focus:outline-none',
        },
    },
    onUpdate: ({ editor: current }) => {
        model.value = current.getHTML()
    },
})

watch(model, (value) => {
    if (editor.value !== undefined && value !== editor.value.getHTML()) {
        editor.value.commands.setContent(value, { emitUpdate: false })
    }
})

onBeforeUnmount(() => editor.value?.destroy())
</script>

<template>
    <div
        class="rounded-md border border-input bg-background shadow-xs focus-within:ring-1 focus-within:ring-ring"
        :class="{ 'border-destructive': invalid }"
    >
        <RichTextToolbar v-if="editor" :editor="editor" :image-upload-url="imageUploadUrl" />
        <EditorContent :editor="editor" />
    </div>
</template>
