import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { router, posted } = vi.hoisted(() => ({
    router: { delete: vi.fn(), put: vi.fn() },
    posted: vi.fn<(url: string, data: unknown) => void>(),
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        router,
        useForm: <T extends object>(data: T) => {
            const form = reactive({
                ...data,
                errors: {},
                processing: false,
                reset() {},
                post(url: string) {
                    posted(
                        url,
                        Object.fromEntries(
                            Object.keys(data).map((key) => [key, Reflect.get(form, key)])
                        )
                    )
                },
            })
            return form
        },
        Link: defineComponent({
            props: { href: { type: String, required: true } },
            setup:
                (props, { slots, attrs }) =>
                () =>
                    h('a', { ...attrs, href: props.href }, slots.default?.()),
        }),
    }
})

import OptionSelect from '@/Components/app/OptionSelect.vue'
import EvaluationSection from '@/Components/FormEngine/EvaluationSection.vue'
import type { EvaluationSectionData, StepEvaluationFormData } from '@/types/form-engine'

const step = (overrides: Partial<StepEvaluationFormData> = {}): StepEvaluationFormData => ({
    id: 41,
    formId: 3,
    title: 'Lembar Substansi',
    versionLabel: 'Versi 2',
    isRequired: true,
    isActive: true,
    assignmentCount: 0,
    ...overrides,
})

const section = (overrides: Partial<EvaluationSectionData> = {}): EvaluationSectionData => ({
    detailId: 7,
    detailLabel: 'Review Proposal — Form Proposal',
    attached: [],
    reviewsInProgress: 0,
    ...overrides,
})

const options = [
    { value: '3', label: 'Lembar Substansi' },
    { value: '5', label: 'Lembar Anggaran' },
]

const route = (name: string, params?: number) =>
    params === undefined ? `/${name}` : `/${name}/${params}`

let wrapper: VueWrapper

const mountSection = (data: EvaluationSectionData) => {
    wrapper = mount(EvaluationSection, {
        props: { section: data, evaluationOptions: options },
        global: { mocks: { route } },
        attachTo: document.body,
    })
    return wrapper
}

const button = (label: string) => {
    const found = Array.from(document.querySelectorAll('button')).find(
        (b) => b.textContent?.trim() === label
    )
    if (found === undefined) throw new Error(`tombol "${label}" tidak ada`)
    return found
}

beforeEach(() => {
    vi.stubGlobal('route', route)
    router.delete.mockClear()
    router.put.mockClear()
    posted.mockClear()
})

afterEach(() => {
    wrapper.unmount()
    document.body.innerHTML = ''
})

// W158 — form hanya ditugaskan otomatis saat reviewer ditugaskan (AssignReviewers); reviewer yang sudah
// bertugas tetap bisa memulai form baru sendiri dari halaman evaluasinya (StartEvaluationForm).
describe('EvaluationSection', () => {
    it('memberi tahu bahwa form baru tidak otomatis ditugaskan ke reviewer yang sedang bekerja', () => {
        expect(mountSection(section({ reviewsInProgress: 2 })).text()).toContain(
            'Form baru tidak otomatis ditugaskan ke reviewer 2 pengajuan yang sedang direview; reviewernya tetap bisa memulainya dari halaman evaluasi.'
        )
    })

    it('diam bila tidak ada pengajuan yang sedang direview', () => {
        mountSection(section())
        expect(wrapper.text()).not.toContain('sedang direview')
    })

    it('mendaftar pemasangan dengan label versinya dan menautkan pustaka', () => {
        mountSection(
            section({
                attached: [
                    step(),
                    step({
                        id: 42,
                        formId: 9,
                        title: 'Lembar Etik',
                        versionLabel: 'Versi 1',
                        isRequired: false,
                        isActive: false,
                    }),
                ],
            })
        )

        expect(wrapper.text()).toContain('Lembar Substansi')
        expect(wrapper.text()).toContain('Versi 2')
        expect(wrapper.text()).toContain('Lembar Etik')
        expect(wrapper.text()).toContain('Nonaktif')
        expect(wrapper.find('a[href="/admin.evaluation-forms.index"]').text()).toBe(
            'Kelola di Form Evaluasi'
        )
    })

    // BR-FE-30 — sekali per langkah: pilihan hanya Form Evaluasi yang belum terpasang di sini.
    it('menawarkan hanya Form Evaluasi yang belum terpasang', () => {
        mountSection(section({ attached: [step()] }))

        expect(wrapper.getComponent(OptionSelect).props('options')).toEqual([
            { value: '5', label: 'Lembar Anggaran' },
        ])
    })

    it('Pasang mengirim form pilihan dan sifat wajibnya ke route langkah', async () => {
        mountSection(section())
        wrapper.getComponent(OptionSelect).vm.$emit('update:modelValue', '5')
        await flushPromises()

        button('Pasang').click()

        expect(posted).toHaveBeenCalledWith('/admin.phase-details.evaluation-forms.store/7', {
            review_evaluation_form_id: '5',
            is_required: true,
        })
    })

    it('Lepas meminta konfirmasi lalu menghapus pemasangan', async () => {
        mountSection(section({ attached: [step()] }))

        button('Lepas').click()
        await flushPromises()
        button('Lepas Form').click()

        expect(router.delete).toHaveBeenCalledWith(
            '/admin.step-evaluation-forms.destroy/41',
            expect.any(Object)
        )
    })

    // BR-FE-22 — pemasangan yang sudah ditugaskan hanya bisa dinonaktifkan.
    it('Lepas pada pemasangan yang sudah ditugaskan menjelaskan dan menawarkan nonaktifkan', async () => {
        mountSection(section({ attached: [step({ assignmentCount: 3 })] }))

        button('Lepas').click()
        await flushPromises()

        expect(document.body.textContent).toContain(
            'Form evaluasi ini sudah ditugaskan ke 3 reviewer di langkah ini, jadi tidak bisa dilepas. Nonaktifkan saja.'
        )
        expect(router.delete).not.toHaveBeenCalled()

        button('Nonaktifkan').click()
        expect(router.put).toHaveBeenCalledWith(
            '/admin.step-evaluation-forms.update/41',
            { is_required: true, is_active: false },
            expect.any(Object)
        )
    })
})
