import { mount } from '@vue/test-utils'
import { defineComponent, h, reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    router: { post: vi.fn() },
    useForm: <T extends object>(data: T) =>
        reactive({ ...data, errors: {}, processing: false, post: vi.fn() }),
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

import EvaluationFormPanel from '@/Components/Review/EvaluationFormPanel.vue'
import EvaluationPage from '@/Pages/Review/EvaluationPage.vue'
import type { EvaluationForm, EvaluationPageProps } from '@/types/review'

const form = (formId: number, title: string): EvaluationForm => ({
    formId,
    assignmentId: null,
    title,
    description: null,
    required: true,
    state: 'not_started',
    dueDate: null,
    submittedAt: null,
    finalNotes: null,
    canEdit: true,
    fields: [],
    versionLabel: 'Versi 1',
})

const baseProps: EvaluationPageProps = {
    submission: {
        id: 9,
        title: 'Analisis Potensi Biochar',
        scheme: 'Penelitian Dasar',
        period: 'Periode 2026',
        leadName: 'Dr. Budi Santoso',
        memberCount: 3,
        status: { key: 'under_review', label: 'Dinilai', variant: 'secondary' },
    },
    answers: [
        {
            label: 'Abstrak',
            value: 'Biochar dari limbah biomassa',
            isFile: false,
            downloadUrl: null,
        },
    ],
    forms: [form(1, 'Formulir Substansi'), form(2, 'Formulir Administrasi')],
    activeFormId: 2,
    evaluationSettled: false,
}

const route = (name: string, params?: unknown) =>
    params === undefined ? `/${name}` : `/${name}/${String(params)}`

const mountPage = (props: Partial<EvaluationPageProps> = {}) =>
    mount(EvaluationPage, {
        props: { ...baseProps, ...props },
        global: { stubs: { teleport: true }, mocks: { route } },
    })

beforeEach(() => vi.stubGlobal('route', route))

describe('EvaluationPage', () => {
    it('tab awal mengikuti activeFormId', () => {
        const wrapper = mountPage()

        const visible = wrapper.findAllComponents(EvaluationFormPanel)
        expect(visible).toHaveLength(1)
        expect(visible[0].props('form').title).toBe('Formulir Administrasi')
        expect(wrapper.text()).toContain('Biochar dari limbah biomassa')
    })

    it('tanpa form evaluasi menampilkan keadaan kosong', () => {
        const wrapper = mountPage({ forms: [], activeFormId: null })

        expect(wrapper.text()).toContain('Tahap ini tidak memiliki formulir evaluasi aktif')
    })

    it('evaluasi selesai menawarkan lanjut ke ringkasan', () => {
        expect(mountPage({ evaluationSettled: true }).text()).toContain(
            'Lanjutkan ke ringkasan dan diskusi'
        )
    })
})
