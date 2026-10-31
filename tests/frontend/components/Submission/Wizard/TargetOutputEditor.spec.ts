import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post, put, destroy } = vi.hoisted(() => ({
    post: vi.fn(),
    put: vi.fn(),
    destroy: vi.fn(),
}))

vi.mock('@inertiajs/vue3', () => ({ router: { post, put, delete: destroy } }))

import OptionSelect from '@/Components/app/OptionSelect.vue'
import TargetOutputEditor from '@/Components/Submission/Wizard/TargetOutputEditor.vue'

const mountEditor = () =>
    mount(TargetOutputEditor, {
        props: {
            submissionId: 7,
            targetOutputs: [{ id: 3, type: 'Jurnal Nasional', description: null }],
            types: [
                { value: 'journal_national', label: 'Jurnal Nasional' },
                { value: 'haki', label: 'HKI' },
            ],
        },
    })

beforeEach(() => {
    vi.stubGlobal(
        'route',
        (name: string, params: unknown) => `/${name}/${[params].flat().join(',')}`
    )
    post.mockReset()
    put.mockReset()
    destroy.mockReset()
})

describe('TargetOutputEditor', () => {
    it('menambah target lewat target-outputs.store', async () => {
        const wrapper = mountEditor()

        wrapper.findComponent(OptionSelect).vm.$emit('update:modelValue', 'journal_national')
        await wrapper.vm.$nextTick()
        await wrapper.find('button[data-action="add-target"]').trigger('click')

        expect(post).toHaveBeenCalledWith(
            '/user.submissions.target-outputs.store/7',
            { output_type: 'journal_national' },
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('mengubah tipe target lewat target-outputs.update', async () => {
        const wrapper = mountEditor()

        await wrapper.find('button[data-action="edit-target"]').trigger('click')
        const editSelect = wrapper.findAllComponents(OptionSelect)[0]
        // Nilai awal dicari dari label karena target membawa nama tipe, bukan kodenya.
        expect(editSelect.props('modelValue')).toBe('journal_national')
        editSelect.vm.$emit('update:modelValue', 'haki')
        await wrapper.vm.$nextTick()
        await wrapper.find('button[data-action="save-target"]').trigger('click')

        expect(put).toHaveBeenCalledWith(
            '/user.submissions.target-outputs.update/7,3',
            { output_type: 'haki' },
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('menghapus lewat target-outputs.destroy', async () => {
        await mountEditor().find('button[data-action="remove-target"]').trigger('click')

        expect(destroy).toHaveBeenCalledWith(
            '/user.submissions.target-outputs.destroy/7,3',
            expect.objectContaining({ preserveScroll: true })
        )
    })
})
