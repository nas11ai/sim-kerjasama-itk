import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import FileInput from '@/Components/app/FileInput.vue'

function pick(input: HTMLInputElement, names: string[]) {
    const files = names.map((name) => new File(['x'], name))
    Object.defineProperty(input, 'files', { value: files, configurable: true })
}

describe('FileInput', () => {
    it('[Paket 2 P1] tombolnya berbahasa Indonesia, bukan teks bawaan peramban', () => {
        const wrapper = mount(FileInput, { props: { id: 'berkas' } })

        expect(wrapper.get('label[for="berkas"]').text()).toBe('Pilih berkas')
        expect(wrapper.get('input[type="file"]').attributes('id')).toBe('berkas')
    })

    it('meneruskan atribut ke input asli dan menampilkan nama berkas terpilih', async () => {
        const wrapper = mount(FileInput, {
            props: { id: 'berkas', multiple: true },
            attrs: { accept: '.pdf', 'aria-describedby': 'petunjuk' },
        })
        const input = wrapper.get('input').element as HTMLInputElement

        expect(input.accept).toBe('.pdf')
        expect(input.multiple).toBe(true)
        expect(input.getAttribute('aria-describedby')).toBe('petunjuk')

        pick(input, ['proposal.pdf'])
        await wrapper.get('input').trigger('change')
        expect(wrapper.text()).toContain('proposal.pdf')
        expect(wrapper.emitted('change')).toHaveLength(1)

        pick(input, ['a.pdf', 'b.pdf'])
        await wrapper.get('input').trigger('change')
        expect(wrapper.text()).toContain('2 berkas dipilih')
    })

    it('tidak menampilkan nama basi bila pemanggil mengosongkan input', async () => {
        const wrapper = mount(FileInput, { props: { id: 'berkas' } })
        const input = wrapper.get('input').element as HTMLInputElement

        pick(input, [])
        await wrapper.get('input').trigger('change')

        expect(wrapper.text()).toBe('Pilih berkas')
    })
})
