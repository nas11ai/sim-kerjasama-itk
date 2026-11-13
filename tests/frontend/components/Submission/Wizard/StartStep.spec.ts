import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post, pageErrors } = vi.hoisted(() => ({
    post: vi.fn(),
    pageErrors: {} as Record<string, string>,
}))

vi.mock('@inertiajs/vue3', () => ({
    router: { post },
    usePage: () => ({ props: { errors: pageErrors } }),
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
        forms: [{ formId: 10, formPhaseId: 5, title: 'Formulir Proposal Penelitian', draftId: null }],
    },
    {
        id: 2,
        name: 'Pengabdian 2026',
        closesAt: null,
        forms: [
            { formId: 20, formPhaseId: 6, title: 'Formulir Pengabdian', draftId: 99 },
            { formId: 21, formPhaseId: 6, title: 'Formulir Pengabdian Mandiri', draftId: null },
        ],
    },
]

const submission: WizardSubmission = {
    id: 7,
    title: 'Formulir Proposal Penelitian',
    status: { key: 'draft', label: 'Draf', variant: 'secondary' },
    period: 'Penelitian DIPA 2026',
    form: 'Formulir Proposal Penelitian',
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
    for (const key of Object.keys(pageErrors)) delete pageErrors[key]
})

describe('StartStep', () => {
    it('menampilkan alasan penolakan Form nonaktif (errors.form_id)', () => {
        pageErrors.form_id = 'Formulir ini sudah tidak menerima pengajuan baru.'

        const wrapper = mountStart()

        expect(wrapper.text()).toContain('Formulir ini sudah tidak menerima pengajuan baru.')
    })

    it('menampilkan alasan penolakan tahap (errors.form_phase_id)', () => {
        pageErrors.form_phase_id = 'Tahap ini belum bertenggat di periode ini.'

        const wrapper = mountStart()

        expect(wrapper.text()).toContain('Tahap ini belum bertenggat di periode ini.')
    })

    it('form tersaring periode terpilih', async () => {
        const wrapper = mountStart()

        await choose(wrapper, 0, '2')

        const forms = wrapper.findAllComponents(OptionSelect)[1].props('options')
        expect(forms.map((form) => form.label)).toEqual([
            'Formulir Pengabdian',
            'Formulir Pengabdian Mandiri',
        ])
    })

    it('menawarkan lanjutkan draft bila ada draftId', async () => {
        const wrapper = mountStart()

        await choose(wrapper, 0, '2')
        await choose(wrapper, 1, '20')

        const link = wrapper.find('a[href="/user.submissions.wizard/99"]')
        expect(link.exists()).toBe(true)
        expect(link.text()).toContain('Lanjutkan draf')
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
            'Formulir dan periode tidak dapat diganti setelah draf dibuat.'
        )
    })
})
