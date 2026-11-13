import { flushPromises, mount } from '@vue/test-utils'
import { reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { routerPost, formPost, forms } = vi.hoisted(() => ({
    routerPost: vi.fn(),
    formPost: vi.fn(),
    forms: [] as Array<Record<string, unknown>>,
}))

vi.mock('@inertiajs/vue3', () => ({
    router: { post: routerPost },
    useForm: <T extends object>(data: T) => {
        const form = reactive({ ...data, errors: {}, processing: false, post: formPost })
        forms.push(form)
        return form
    },
}))

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import EvaluationFormPanel from '@/Components/Review/EvaluationFormPanel.vue'
import type { EvaluationForm } from '@/types/review'

const draft: EvaluationForm = {
    formId: 4,
    assignmentId: 31,
    title: 'Form Substansi',
    description: null,
    required: true,
    state: 'draft',
    dueDate: '2026-11-07T00:00:00.000000Z',
    submittedAt: null,
    finalNotes: 'Catatan lama',
    canEdit: true,
    versionLabel: 'Versi 1',
    fields: [
        {
            id: 5,
            label: 'Originalitas',
            type: 'text',
            required: true,
            value: '6',
            options: [],
            fileName: null,
            downloadUrl: null,
        },
        {
            id: 6,
            label: 'Metodologi (1–7)',
            type: 'select',
            required: true,
            value: '5',
            options: [{ value: '5', label: '5: Baik' }],
            fileName: null,
            downloadUrl: null,
        },
    ],
}

const route = (name: string, params?: unknown) =>
    params === undefined ? `/${name}` : `/${name}/${String(params)}`

const mountPanel = (form: Partial<EvaluationForm> = {}) =>
    mount(EvaluationFormPanel, {
        props: { submissionId: 9, form: { ...draft, ...form } },
        global: { stubs: { teleport: true }, mocks: { route } },
    })

beforeEach(() => {
    vi.stubGlobal('route', route)
    routerPost.mockReset()
    formPost.mockReset()
    forms.length = 0
})

describe('EvaluationFormPanel', () => {
    it('[REV-VER-05] kepala lembar menyebut versi yang diisi', () => {
        expect(mountPanel().find('[data-version]').text()).toBe('Versi 1')
    })

    it('form belum dimulai menawarkan Mulai Evaluasi', async () => {
        const wrapper = mountPanel({ state: 'not_started', assignmentId: null })

        await wrapper.find('button[data-action="start"]').trigger('click')

        expect(routerPost).toHaveBeenCalledWith(
            '/reviewer.evaluation-form.start',
            { submission_id: 9, review_evaluation_form_id: 4 },
            expect.anything()
        )
        expect(wrapper.find('button[data-action="save"]').exists()).toBe(false)
    })

    it('[REV-EVAL-01] draft disimpan lewat save-draft dengan jawaban field', async () => {
        const wrapper = mountPanel()

        await wrapper.find('input#field-5').setValue('7')
        await wrapper.find('button[data-action="save"]').trigger('click')

        expect(forms[0].responses).toEqual({ '5': '7', '6': '5' })
        expect(forms[0].final_notes).toBe('Catatan lama')
        expect(formPost).toHaveBeenCalledWith(
            '/reviewer.evaluation-form.save-draft/31',
            expect.anything()
        )
    })

    it('[REV-EVAL-03] submit evaluasi lewat dialog konfirmasi', async () => {
        const wrapper = mountPanel()

        await wrapper.find('form').trigger('submit')
        await flushPromises()
        expect(formPost).not.toHaveBeenCalled()

        wrapper.findComponent(ConfirmDialog).vm.$emit('confirm')

        expect(formPost).toHaveBeenCalledWith(
            '/reviewer.evaluation-form.submit/31',
            expect.anything()
        )
    })

    it('[REV-EVAL-02] evaluasi terkirim tampil terkunci tanpa tombol simpan', () => {
        const wrapper = mountPanel({
            state: 'submitted',
            canEdit: false,
            submittedAt: '2026-11-01T07:20:00.000000Z',
        })

        expect(wrapper.text()).toContain('Penilaian sudah dikirim dan tidak dapat diubah.')
        // Opsi ditampilkan dengan labelnya, bukan nilai mentah.
        expect(wrapper.text()).toContain('5: Baik')
        expect(wrapper.find('form').exists()).toBe(false)
    })

    it('[FE-WINDOW-02] tenggat lewat menutup isian', () => {
        const wrapper = mountPanel({ state: 'overdue', canEdit: false })

        expect(wrapper.text()).toContain('Batas waktu evaluasi sudah lewat.')
        expect(wrapper.find('form').exists()).toBe(false)
    })

    it('kesalahan kiriman ditampilkan', async () => {
        const wrapper = mountPanel()

        forms[0].errors = { responses: "Field 'Originalitas' wajib diisi." }
        await flushPromises()

        expect(wrapper.text()).toContain("Field 'Originalitas' wajib diisi.")
    })
})
