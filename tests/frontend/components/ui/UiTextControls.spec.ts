import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import UiInput from '@/Components/ui/input/UiInput.vue'
import UiTextarea from '@/Components/ui/textarea/UiTextarea.vue'

// F99 Task 4 — karakterisasi sebelum `useTextModel` diekstrak dari keduanya.
describe.each([
    ['UiInput', UiInput],
    ['UiTextarea', UiTextarea],
])('%s', (_name, Control) => {
    it('menampilkan defaultValue tanpa modelValue', () => {
        const wrapper = mount(Control, { props: { defaultValue: 'awal' } })
        expect((wrapper.element as HTMLInputElement).value).toBe('awal')
    })

    it('memancarkan update:modelValue saat diketik', async () => {
        const wrapper = mount(Control, { props: { modelValue: 'lama' } })
        await wrapper.setValue('baru')
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['baru'])
    })

    it('mengikuti modelValue dari induk', async () => {
        const wrapper = mount(Control, { props: { modelValue: 'satu' } })
        await wrapper.setProps({ modelValue: 'dua' })
        expect((wrapper.element as HTMLInputElement).value).toBe('dua')
    })

    it('menggabungkan class tambahan', () => {
        const wrapper = mount(Control, { props: { class: 'kelas-uji' } })
        expect(wrapper.classes()).toContain('kelas-uji')
    })
})
