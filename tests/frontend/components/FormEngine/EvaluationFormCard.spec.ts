import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { submitted } = vi.hoisted(() => ({
    submitted: vi.fn<(method: string, url: string, data: unknown) => void>(),
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        useForm: <T extends object>(data: T) => {
            let transformer = (value: T): unknown => value
            const form = reactive({
                ...data,
                errors: {},
                processing: false,
                transform(fn: (value: T) => unknown) {
                    transformer = fn
                    return form
                },
                put(url: string) {
                    submitted('put', url, transformer({ ...data, ...form }))
                },
                post(url: string) {
                    submitted('post', url, transformer({ ...data, ...form }))
                },
            })
            return form
        },
    }
})

import OptionSelect from '@/Components/app/OptionSelect.vue'
import EvaluationFormCard from '@/Components/FormEngine/EvaluationFormCard.vue'
import type {
    EvaluationFieldEditData,
    EvaluationFormEditData,
    FieldTypeOption,
} from '@/types/form-engine'

const types: FieldTypeOption[] = [
    { value: '1', label: 'Teks', hasOptions: false, limitKind: 'length' },
    { value: '3', label: 'Angka', hasOptions: false, limitKind: 'value' },
]

const field = (overrides: Partial<EvaluationFieldEditData> = {}): EvaluationFieldEditData => ({
    id: 11,
    label: 'Metodologi',
    description: null,
    fieldTypeId: 3,
    isRequired: true,
    limits: { minLength: null, maxLength: null, minValue: 1, maxValue: 7 },
    answerCount: 0,
    options: [],
    ...overrides,
})

const evaluation = (fields: EvaluationFieldEditData[]): EvaluationFormEditData => ({
    id: 9,
    title: 'Lembar Substansi',
    description: null,
    isRequired: true,
    isActive: true,
    assignmentCount: 0,
    fields,
})

const route = (name: string, params?: number | Record<string, number>) =>
    [`/${name}`, typeof params === 'object' ? Object.values(params).join('/') : params]
        .filter((part) => part !== undefined)
        .join('/')

let wrapper: VueWrapper

const mountCard = (form: EvaluationFormEditData | null) => {
    wrapper = mount(EvaluationFormCard, {
        props: { detailId: 31, form, fieldTypeOptions: types },
        global: { mocks: { route } },
        attachTo: document.body,
    })
    return wrapper
}

const button = (label: string) => {
    const found = Array.from(document.querySelectorAll('button')).find(
        (b) => b.textContent?.trim() === label || b.getAttribute('aria-label') === label
    )
    if (found === undefined) throw new Error(`tombol "${label}" tidak ada`)
    return found
}

beforeEach(() => {
    vi.stubGlobal('route', route)
    submitted.mockClear()
})

afterEach(() => {
    wrapper.unmount()
    document.body.innerHTML = ''
})

describe('FormEngine/EvaluationFormCard', () => {
    it('judul kartu dan isian form evaluasi dari frame', () => {
        mountCard(evaluation([field()]))

        expect(wrapper.text()).toContain('Form Evaluasi — Lembar Substansi')
        expect(wrapper.text()).toContain('Tambah Field')
        expect(wrapper.text()).toContain('Wajib diisi reviewer')
        expect(wrapper.text()).toContain('Metodologi')
    })

    it('field yang sudah dijawab reviewer: tipe dan hapus terkunci', async () => {
        mountCard(evaluation([field({ answerCount: 3 })]))

        expect(button('Hapus Metodologi').disabled).toBe(true)
        button('Edit Metodologi').click()
        await flushPromises()

        expect(wrapper.findComponent(OptionSelect).exists()).toBe(true)
        const typeSelect = wrapper
            .findAllComponents(OptionSelect)
            .find((select) => select.props('id') === 'evaluation-field-type')
        expect(typeSelect?.props('disabled')).toBe(true)
        expect(document.body.textContent).toContain(
            'Field ini sudah dijawab reviewer; buat field baru untuk tipe lain.'
        )
    })

    it('field yang belum dijawab bisa dihapus dari kartu', async () => {
        mountCard(evaluation([field(), field({ id: 12, label: 'Luaran' })]))

        button('Hapus Metodologi').click()
        await flushPromises()
        button('Simpan Form Evaluasi').click()
        await flushPromises()

        const [, , data] = submitted.mock.calls[0]
        expect(data).toMatchObject({ fields: [{ id: 12, label: 'Luaran' }] })
    })

    it('kartu tersimpan memanggil put ke admin.evaluation-forms.update', async () => {
        mountCard(evaluation([field()]))

        button('Simpan Form Evaluasi').click()
        await flushPromises()

        expect(submitted).toHaveBeenCalledWith(
            'put',
            '/admin.evaluation-forms.update/9',
            expect.objectContaining({
                title: 'Lembar Substansi',
                is_required: true,
                is_active: true,
                fields: [expect.objectContaining({ id: 11, field_type_id: 3 })],
            })
        )
    })

    it('kartu baru memanggil post ke admin.phase-details.evaluation-forms.store', async () => {
        mountCard(null)

        button('Simpan Form Evaluasi').click()
        await flushPromises()

        expect(submitted).toHaveBeenCalledWith(
            'post',
            '/admin.phase-details.evaluation-forms.store/31',
            expect.objectContaining({ fields: [] })
        )
    })
})
