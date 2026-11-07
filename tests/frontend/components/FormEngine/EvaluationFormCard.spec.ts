import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { submitted, deleted } = vi.hoisted(() => ({
    submitted: vi.fn<(method: string, url: string, data: unknown) => void>(),
    deleted: vi.fn<(url: string) => void>(),
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        router: { delete: deleted },
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
    { value: '1', label: 'Teks', hasOptions: false, limitType: 'length' },
    { value: '3', label: 'Angka', hasOptions: false, limitType: 'value' },
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

const evaluation = (
    fields: EvaluationFieldEditData[],
    overrides: Partial<EvaluationFormEditData> = {}
): EvaluationFormEditData => ({
    id: 9,
    title: 'Lembar Substansi',
    description: null,
    isRequired: true,
    isActive: true,
    assignmentCount: 0,
    fields,
    ...overrides,
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

const button = (label: string, nth = 0) => {
    const found = Array.from(document.querySelectorAll('button')).filter(
        (b) => b.textContent?.trim() === label || b.getAttribute('aria-label') === label
    )[nth]
    if (found === undefined) throw new Error(`tombol "${label}" tidak ada`)
    return found
}

beforeEach(() => {
    vi.stubGlobal('route', route)
    submitted.mockClear()
    deleted.mockClear()
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

    it('Tambah Field: label wajib, lalu baris baru masuk kartu', async () => {
        mountCard(evaluation([]))

        button('Tambah Field').click()
        await flushPromises()
        button('Simpan Field').click()
        await flushPromises()
        expect(document.body.textContent).toContain('Label wajib diisi')

        const label = document.querySelector<HTMLInputElement>('#evaluation-field-label')
        if (label === null) throw new Error('isian label tidak ada')
        label.value = 'Catatan Kualitatif'
        label.dispatchEvent(new Event('input'))
        await flushPromises()
        button('Simpan Field').click()
        await flushPromises()

        expect(wrapper.text()).toContain('Catatan Kualitatif')
        expect(wrapper.text()).not.toContain('Belum ada field.')
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

    it('[BR-FE-22] form dinonaktifkan dari header: Status ikut terkini tanpa pasang ulang', async () => {
        mountCard(evaluation([field()]))

        await wrapper.setProps({ form: evaluation([field()], { isActive: false }) })
        await flushPromises()
        button('Simpan Form Evaluasi').click()
        await flushPromises()

        expect(wrapper.text()).toContain('Nonaktif')
        expect(submitted).toHaveBeenCalledWith(
            'put',
            '/admin.evaluation-forms.update/9',
            expect.objectContaining({ is_active: false })
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

    it('[FE-DELETE-13] hapus form evaluasi yang pernah ditugaskan membuka penjelasan berangka', async () => {
        mountCard(evaluation([field()], { assignmentCount: 4 }))

        button('Hapus').click()
        await flushPromises()

        expect(document.body.textContent).toContain('Form evaluasi tidak bisa dihapus')
        expect(document.body.textContent).toContain('Form ini pernah ditugaskan 4 kali.')
        expect(button('Mengerti')).toBeDefined()
        expect(button('Nonaktifkan')).toBeDefined()
        expect(deleted).not.toHaveBeenCalled()
    })

    it('[FE-DELETE-14] form evaluasi yang belum dipakai dikonfirmasi lalu dihapus', async () => {
        mountCard(evaluation([field()]))

        button('Hapus').click()
        await flushPromises()
        expect(deleted).not.toHaveBeenCalled()
        button('Hapus', 2).click()
        await flushPromises()

        expect(deleted).toHaveBeenCalledWith('/admin.evaluation-forms.destroy/9', expect.anything())
    })

    it('nonaktifkan dikonfirmasi lalu memanggil put tanpa kunci fields', async () => {
        mountCard(evaluation([field()]))

        button('Nonaktifkan').click()
        await flushPromises()
        expect(document.body.textContent).toContain('Nonaktifkan form evaluasi?')
        button('Nonaktifkan', 1).click()
        await flushPromises()

        expect(submitted).toHaveBeenCalledTimes(1)
        const [method, url, data] = submitted.mock.calls[0]
        expect(method).toBe('put')
        expect(url).toBe('/admin.evaluation-forms.update/9')
        expect(data).toMatchObject({ title: 'Lembar Substansi', is_active: false })
        expect(data).not.toHaveProperty('fields')
    })

    it('form nonaktif ditawarkan Aktifkan', async () => {
        mountCard(evaluation([field()], { isActive: false }))

        button('Aktifkan').click()
        await flushPromises()
        button('Aktifkan', 1).click()
        await flushPromises()

        expect(submitted.mock.calls[0][2]).toMatchObject({ is_active: true })
    })

    it('aktifkan ulang memakai data terkini, bukan potret saat kartu dipasang', async () => {
        mountCard(evaluation([field()]))
        await wrapper.setProps({
            form: evaluation([field()], { isActive: false, title: 'Judul Baru' }),
        })

        button('Aktifkan').click()
        await flushPromises()
        button('Aktifkan', 1).click()
        await flushPromises()

        const [, url, data] = submitted.mock.calls[0]
        expect(url).toBe('/admin.evaluation-forms.update/9')
        expect(data).toMatchObject({ title: 'Judul Baru', is_active: true })
        expect(data).not.toHaveProperty('fields')
    })
})
