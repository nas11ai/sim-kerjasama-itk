import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { submitted, formErrors } = vi.hoisted(() => ({
    submitted: vi.fn<(method: string, url: string, data: Record<string, unknown>) => void>(),
    formErrors: { value: {} as Record<string, string> },
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        useForm: <T extends object>(data: T) => {
            const form = reactive({
                ...data,
                errors: formErrors.value,
                processing: false,
                post(url: string) {
                    submitted('post', url, JSON.parse(JSON.stringify(form)))
                },
                put(url: string) {
                    submitted('put', url, JSON.parse(JSON.stringify(form)))
                },
            })
            return form
        },
        Head: defineComponent({ setup: () => () => null }),
        Link: defineComponent({
            props: { href: { type: String, required: true } },
            setup:
                (props, { slots }) =>
                () =>
                    h('a', { href: props.href }, slots.default?.()),
        }),
    }
})
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}))

import SchemeFormPage from '@/Pages/Scheme/FormPage.vue'
import type { SchemeEditData, SchemeFormPageProps } from '@/types/scheme'

const scheme = (overrides: Partial<SchemeEditData> = {}): SchemeEditData => ({
    id: 7,
    name: 'Penelitian DIPA',
    code: 'PEN-DIPA',
    isActive: true,
    schemeTypeId: 2,
    submissionTypeId: 1,
    maxBudget: 50_000_000,
    maxMembers: 5,
    durationMonths: 12,
    allowedTrlIds: [3],
    rules: {
        min_reviewer_count: 3,
        max_reviewer_workload: null,
        max_student_members: null,
        allowed_output_types: ['jurnal'],
    },
    ...overrides,
})

const props = (value: SchemeEditData | null): SchemeFormPageProps => ({
    scheme: value,
    options: {
        schemeTypes: [{ value: '2', label: 'DIPA' }],
        submissionTypes: [{ value: '1', label: 'Penelitian' }],
        trls: [
            { value: '3', label: 'TRL 3' },
            { value: '4', label: 'TRL 4' },
        ],
        outputTypes: [
            { value: 'jurnal', label: 'Jurnal' },
            { value: 'hki', label: 'HKI' },
        ],
        integerRules: [
            { value: 'min_reviewer_count', label: 'Minimal Reviewer' },
            { value: 'max_reviewer_workload', label: 'Beban Maks. Reviewer' },
            { value: 'max_student_members', label: 'Anggota Mahasiswa Maks.' },
        ],
    },
})

const route = (name: string, id?: number) => (id === undefined ? `/${name}` : `/${name}/${id}`)

const mountPage = (value: SchemeEditData | null) =>
    mount(SchemeFormPage, { props: props(value), global: { mocks: { route } } })

const submit = async (value: SchemeEditData | null) => {
    // Tombol submit hanya memicu `submit` bila form menempel ke dokumen.
    const wrapper = mount(SchemeFormPage, {
        props: props(value),
        global: { mocks: { route } },
        attachTo: document.body,
    })
    await wrapper
        .findAll('button')
        .find((b) => b.text().trim() === 'Simpan')
        ?.trigger('click')
    await flushPromises()
    wrapper.unmount()
}

beforeEach(() => {
    vi.stubGlobal('route', route)
    submitted.mockClear()
    formErrors.value = {}
})

describe('Scheme/FormPage', () => {
    it('judul mengikuti mode buat dan sunting', () => {
        expect(mountPage(null).text()).toContain('Buat Skema Baru')
        const edit = mountPage(scheme()).text()
        expect(edit).toContain('Edit Skema — Penelitian DIPA')
        expect(edit).toContain(
            'Atur ketentuan skema: batasan utama, aturan tambahan, dan TRL yang diizinkan.'
        )
        expect(edit).toContain('Aktif')
        expect(edit).toContain('Kode harus unik antar skema.')
        expect(edit).toContain('Aturan tambahan langsung berlaku pada skema ini.')
    })

    it('[SCH-RULE-01] galat kode tampil di field', () => {
        formErrors.value = { code: 'Kode ini sudah dipakai skema lain.' }
        const wrapper = mountPage(null)

        expect(wrapper.find('#scheme-code-message').text()).toBe(
            'Kode ini sudah dipakai skema lain.'
        )
        expect(wrapper.text()).not.toContain('Kode harus unik antar skema.')
        expect(wrapper.text()).toContain('Kode skema harus unik.')
    })

    it('[SCH-RULE-02] galat batas nol tampil di field', () => {
        formErrors.value = {
            max_budget: 'Harus lebih dari nol.',
            max_members: 'Harus lebih dari nol.',
        }
        const wrapper = mountPage(null)

        expect(wrapper.find('#scheme-budget-message').text()).toBe('Harus lebih dari nol.')
        expect(wrapper.find('#scheme-members-message').text()).toBe('Harus lebih dari nol.')
        expect(wrapper.text()).toContain('Batas anggaran dan jumlah anggota harus lebih dari nol.')
    })

    it('membuat memanggil store, menyunting memanggil update dengan rules dan allowed_trl_ids', async () => {
        await submit(null)
        expect(submitted).toHaveBeenLastCalledWith(
            'post',
            '/admin.schemes.store',
            expect.objectContaining({
                rules: {
                    min_reviewer_count: '',
                    max_reviewer_workload: '',
                    max_student_members: '',
                    allowed_output_types: [],
                },
                allowed_trl_ids: [],
            })
        )

        await submit(scheme())
        expect(submitted).toHaveBeenLastCalledWith(
            'put',
            '/admin.schemes.update/7',
            expect.objectContaining({
                name: 'Penelitian DIPA',
                scheme_type_id: '2',
                rules: {
                    min_reviewer_count: '3',
                    max_reviewer_workload: '',
                    max_student_members: '',
                    allowed_output_types: ['jurnal'],
                },
                allowed_trl_ids: [3],
            })
        )
    })

    it('TRL terpilih ditandai aria-pressed dan bisa diganti', async () => {
        const wrapper = mountPage(scheme())
        const chip = (label: string) =>
            wrapper.findAll('button[aria-pressed]').find((b) => b.text() === label)

        expect(chip('TRL 3')?.attributes('aria-pressed')).toBe('true')
        expect(chip('TRL 4')?.attributes('aria-pressed')).toBe('false')
        expect(wrapper.text()).toContain('Klik untuk memilih — tanda penuh berarti terpilih.')

        await chip('TRL 4')?.trigger('click')
        await chip('TRL 3')?.trigger('click')
        expect(chip('TRL 4')?.attributes('aria-pressed')).toBe('true')
        expect(chip('TRL 3')?.attributes('aria-pressed')).toBe('false')
    })
})
