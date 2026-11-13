import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))

import RevisionEditPanel from '@/Components/Submission/Revision/RevisionEditPanel.vue'

beforeEach(() => {
    vi.stubGlobal('route', (name: string, params: unknown[]) => `/${name}/${params.join('/')}`)
})

describe('RevisionEditPanel', () => {
    it('[SM-SUBMIT-19] mendaftar field wajib kosong dengan tautan langkah wizard', () => {
        const wrapper = mount(RevisionEditPanel, {
            props: {
                submissionId: 7,
                canEdit: true,
                missingFields: [
                    { fieldId: 1, label: 'Proposal PDF', message: 'Wajib diunggah.', step: 6 },
                    { fieldId: 2, label: 'Abstrak', message: 'Wajib diisi.', step: 2 },
                ],
            },
        })

        expect(wrapper.text()).toContain('2 isian wajib masih kosong.')
        expect(wrapper.find('a[href="/user.submissions.wizard/7/6"]').text()).toBe('Proposal PDF')
        expect(wrapper.find('a[href="/user.submissions.wizard/7/2"]').exists()).toBe(true)
        expect(wrapper.text()).toContain('Rencana anggaran masih dapat diubah selama revisi.')
    })

    it('tanpa hak sunting, tautan langkah wizard tidak dirender', () => {
        const wrapper = mount(RevisionEditPanel, {
            props: { submissionId: 7, canEdit: false, missingFields: [] },
        })

        expect(wrapper.findAll('a')).toHaveLength(0)
    })
})
