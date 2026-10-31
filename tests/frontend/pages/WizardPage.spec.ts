import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post } = vi.hoisted(() => ({ post: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
    router: { post },
    usePage: () => ({ props: { errors: {} } }),
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('a', slots.default?.()),
    }),
}))
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}))

import WizardPage from '@/Pages/Submission/WizardPage.vue'
import type { SubmissionWizardPageProps, WizardField, WizardStep } from '@/types/submission-wizard'

const steps: WizardStep[] = [1, 2, 3, 4, 5, 6, 7].map((number) => ({
    number,
    label: `Langkah ${number}`,
    completed: false,
    blockedReason: null,
}))

const field = (id: number, label: string): WizardField => ({
    id,
    label,
    type: 'text',
    required: false,
    value: null,
    options: [],
    fileName: null,
    downloadUrl: null,
})

const createProps: SubmissionWizardPageProps = {
    step: 1,
    steps,
    submission: null,
    startOptions: [],
    fields: [],
    targetOutputs: [],
    targetOutputTypes: [],
    review: null,
    members: null,
    budget: null,
    can: null,
}

const editProps = (step: number, fields: WizardField[]): Partial<SubmissionWizardPageProps> => ({
    step,
    fields,
    submission: {
        id: 7,
        title: 'Form Proposal',
        status: { key: 'draft', label: 'Draft', variant: 'secondary' },
        period: 'DIPA 2026',
        form: 'Form Proposal',
        lastUpdatedAt: '2026-10-31T01:00:00.000000Z',
    },
    can: { view: true, update: true, submit: true, resubmit: false, withdraw: false },
})

const route = (name: string, params?: unknown) =>
    params === undefined ? `/${name}` : `/${name}/${[params].flat().join(',')}`

const mountPage = (props: SubmissionWizardPageProps) =>
    mount(WizardPage, { props, global: { mocks: { route } } })

beforeEach(() => {
    vi.stubGlobal('route', route)
    post.mockReset()
})

// Inertia memakai ulang instance halaman saat komponennya sama: props berganti, setup tidak diulang.
describe('WizardPage', () => {
    it('langkah 1 → 2 tanpa dipasang ulang memuat isian langkah 2', async () => {
        const wrapper = mountPage(createProps)

        await wrapper.setProps(editProps(2, [field(1, 'Judul Penelitian')]))

        expect(wrapper.find('input#field-1').exists()).toBe(true)
    })

    it('langkah 2 → 6 mengirim field langkah 6, bukan langkah 2', async () => {
        const wrapper = mountPage({ ...createProps, ...editProps(2, [field(1, 'Judul')]) })

        await wrapper.setProps(editProps(6, [field(9, 'Catatan Berkas')]))
        await wrapper.find('input#field-9').setValue('isi')
        await wrapper
            .findAll('button')
            .find((b) => b.text().includes('Lanjut'))
            ?.trigger('click')

        expect(post.mock.calls[0][0]).toBe('/user.submissions.wizard.save/7,6')
        expect(post.mock.calls[0][1].responses).toEqual([{ form_field_id: 9, value: 'isi' }])
    })

    it('langkah 4 dan 5 dirender di dalam wizard draft', async () => {
        const wrapper = mountPage({
            ...createProps,
            ...editProps(4, []),
            members: {
                members: [],
                students: [],
                partners: [],
                memberLimit: null,
                studentLimit: null,
                roleOptions: [],
            },
        })

        expect(wrapper.text()).toContain('Step 4 — Anggota Penelitian')

        await wrapper.setProps({
            ...editProps(5, []),
            members: null,
            budget: {
                items: [],
                total: 0,
                maxBudget: null,
                components: [],
                blockedReason: null,
                editable: true,
            },
        })

        expect(wrapper.text()).toContain('Step 5 — Rencana Anggaran')
        expect(wrapper.text()).not.toContain('irisan berikutnya')
    })
})
