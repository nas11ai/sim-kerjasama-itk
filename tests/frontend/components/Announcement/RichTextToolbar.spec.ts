import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@/Components/Announcement/RichTextLinkPopover.vue', () => ({
    default: { render: () => null },
}))
vi.mock('@/Components/Announcement/RichTextImagePopover.vue', () => ({
    default: { render: () => null },
}))

import RichTextToolbar from '@/Components/Announcement/RichTextToolbar.vue'

// Editor palsu secukupnya: toolbar hanya memanggil isActive dan chain().focus().<perintah>().run().
const run = vi.fn()
const chain = new Proxy({}, { get: (_t, key) => (key === 'run' ? run : () => chain) })
const editor = { isActive: () => false, chain: () => chain }

describe('RichTextToolbar', () => {
    it('tombol format tidak merebut fokus editor saat ditekan', () => {
        const wrapper = mount(RichTextToolbar, { props: { editor, imageUploadUrl: '/u' } })
        const bold = wrapper.get('button[aria-label="Tebal"]').element

        const event = new MouseEvent('mousedown', { bubbles: true, cancelable: true })
        bold.dispatchEvent(event)

        // Fokus pindah ke tombol membuat ketikan berikutnya hilang sampai Tiptap
        // mengembalikan fokus pada frame berikutnya.
        expect(event.defaultPrevented).toBe(true)
    })

    it('klik tombol menjalankan perintah editor', async () => {
        const wrapper = mount(RichTextToolbar, { props: { editor, imageUploadUrl: '/u' } })

        await wrapper.get('button[aria-label="Urungkan"]').trigger('click')

        expect(run).toHaveBeenCalled()
    })
})
