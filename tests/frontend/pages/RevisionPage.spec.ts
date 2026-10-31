import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h, reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post } = vi.hoisted(() => ({ post: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
    router: { post },
    useForm: <T extends object>(data: T) =>
        reactive({ ...data, errors: {}, processing: false, post: vi.fn(), reset: vi.fn() }),
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

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import RevisionPage from '@/Pages/Submission/RevisionPage.vue'
import type { SubmissionAbilities } from '@/types/submission-page'
import type { SubmissionRevisionPageProps } from '@/types/submission-revision'

const can: SubmissionAbilities = {
    view: true,
    update: true,
    submit: false,
    resubmit: true,
    withdraw: false,
    manageTargetOutput: true,
    discussReview: true,
}

const baseProps: SubmissionRevisionPageProps = {
    submission: {
        id: 7,
        title: 'Analisis Potensi Biochar',
        status: { key: 'needs_revision', label: 'Perlu Revisi', variant: 'outline' },
    },
    threads: [],
    missingFields: [],
    accountBlock: null,
    can,
}

const route = (name: string, params: unknown) => `/${name}/${String(params)}`

const mountPage = (props: Partial<SubmissionRevisionPageProps> = {}) =>
    mount(RevisionPage, {
        props: { ...baseProps, ...props },
        global: { stubs: { teleport: true }, mocks: { route } },
    })

const resubmitButton = (wrapper: ReturnType<typeof mountPage>) =>
    wrapper.find('button[data-action="resubmit"]')

beforeEach(() => {
    vi.stubGlobal('route', route)
    post.mockReset()
})

describe('RevisionPage', () => {
    it('[SM-SUBMIT-12] ketua mengirim ulang lewat submissions.resubmit', async () => {
        const wrapper = mountPage()

        await resubmitButton(wrapper).trigger('click')
        await flushPromises()
        wrapper.findComponent(ConfirmDialog).vm.$emit('confirm')

        expect(post).toHaveBeenCalledWith(
            '/user.submissions.resubmit/7',
            {},
            expect.objectContaining({ onFinish: expect.any(Function) })
        )
    })

    it('[SM-SUBMIT-19] Kirim Ulang nonaktif selama field wajib kosong', () => {
        const wrapper = mountPage({
            missingFields: [{ fieldId: 1, label: 'Proposal PDF', message: 'Wajib.', step: 6 }],
        })

        expect(resubmitButton(wrapper).attributes('disabled')).toBeDefined()
    })

    it('[SM-SUBMIT-13] bukan ketua: Kirim Ulang tidak dirender', () => {
        const wrapper = mountPage({ can: { ...can, resubmit: false, discussReview: false } })

        expect(resubmitButton(wrapper).exists()).toBe(false)
        expect(wrapper.text()).toContain(
            'Hanya ketua pengaju yang dapat mengirim ulang pengajuan ini.'
        )
    })

    it('ulir kosong menampilkan keadaan kosong', () => {
        expect(mountPage().text()).toContain('Belum ada catatan reviewer')
    })
})
