import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post } = vi.hoisted(() => ({ post: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
    router: { post },
    usePage: () => ({ props: { errors: {} } }),
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))

import OptionSelect from '@/Components/app/OptionSelect.vue'
import StartStep from '@/Components/Submission/Wizard/StartStep.vue'
import type { StartPeriodOption, WizardSubmission } from '@/types/submission-wizard'

const options: StartPeriodOption[] = [
    {
        id: 1,
        name: 'Penelitian DIPA 2026',
        closesAt: '2026-12-15T00:00:00.000000Z',
        forms: [{ formId: 10, formPhaseId: 5, title: 'Form Proposal Penelitian', draftId: null }],
    },
    {
        id: 2,
        name: 'Pengabdian 2026',
        closesAt: null,
        forms: [
            { formId: 20, formPhaseId: 6, title: 'Form Pengabdian', draftId: 99 },
            { formId: 21, formPhaseId: 6, title: 'Form Pengabdian Mandiri', draftId: null },
        ],
    },
]

const submission: WizardSubmission = {
    id: 7,
    title: 'Form Proposal Penelitian',
    status: { key: 'draft', label: 'Draft', variant: 'secondary' },
    period: 'Penelitian DIPA 2026',
    form: 'Form Proposal Penelitian',
    lastUpdatedAt: '2026-10-31T01:00:00.000000Z',
}

const mountStart = (props: { submission?: WizardSubmission | null } = {}) =>
    mount(StartStep, { props: { submission: props.submission ?? null, options } })

const choose = async (wrapper: ReturnType<typeof mountStart>, index: number, value: string) => {
    wrapper.findAllComponents(OptionSelect)[index].vm.$emit('update:modelValue', value)
    await wrapper.vm.$nextTick()
}

beforeEach(() => {
    vi.stubGlobal('route', (name: string, params?: unknown) =>
        params === undefined ? `/${name}` : `/${name}/${String(params)}`
    )
    post.mockReset()
})

describe('StartStep', () => {
    it('form tersaring periode terpilih', async () => {
        const wrapper = mountStart()

        await choose(wrapper, 0, '2')

        const forms = wrapper.findAllComponents(OptionSelect)[1].props('options')
        expect(forms.map((form) => form.label)).toEqual([
            'Form Pengabdian',
            'Form Pengabdian Mandiri',
        ])
    })

    it('menawarkan lanjutkan draft bila ada draftId', async () => {
        const wrapper = mountStart()

        await choose(wrapper, 0, '2')
        await choose(wrapper, 1, '20')

        const link = wrapper.find('a[href="/user.submissions.wizard/99"]')
        expect(link.exists()).toBe(true)
        expect(link.text()).toContain('Lanjutkan draft')
    })

    it('Lanjut memanggil router.post ke user.submissions.store dengan form_phase_id', async () => {
        const wrapper = mountStart()

        await choose(wrapper, 0, '1')
        await choose(wrapper, 1, '10')
        await wrapper.find('button[data-action="start"]').trigger('click')

        expect(post).toHaveBeenCalledWith(
            '/user.submissions.store',
            { submission_period_id: 1, form_id: 10, form_phase_id: 5 },
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('draft yang ada tampil hanya-baca', () => {
        const wrapper = mountStart({ submission })

        expect(wrapper.findAllComponents(OptionSelect)).toHaveLength(0)
        expect(wrapper.text()).toContain('Penelitian DIPA 2026')
        expect(wrapper.text()).toContain(
            'Form dan periode tidak dapat diganti setelah draft dibuat.'
        )
    })
})
