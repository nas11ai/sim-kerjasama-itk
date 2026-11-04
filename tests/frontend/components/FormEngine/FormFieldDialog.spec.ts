import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'

import OptionSelect from '@/Components/app/OptionSelect.vue'
import FormFieldDialog from '@/Components/FormEngine/FormFieldDialog.vue'
import type { EditableField } from '@/lib/formDefinition'
import type { FieldTypeOption } from '@/types/form-engine'

const types: FieldTypeOption[] = [
    { value: '1', label: 'Teks', hasOptions: false, limitKind: 'length' },
    { value: '5', label: 'Pilihan (dropdown)', hasOptions: true, limitKind: null },
]

const field = (overrides: Partial<EditableField> = {}): EditableField => ({
    key: 'field-11',
    id: 11,
    label: 'Judul',
    fieldTypeId: 1,
    isRequired: false,
    requiredSince: null,
    answerCount: 0,
    options: [],
    ...overrides,
})

const text = () => document.body.textContent ?? ''
const button = (label: string) => {
    const found = Array.from(document.querySelectorAll('button')).find(
        (b) => b.textContent?.trim() === label
    )
    if (found === undefined) throw new Error(`tombol "${label}" tidak ada`)
    return found
}

let wrapper: VueWrapper

const open = async (value: EditableField | null) => {
    wrapper = mount(FormFieldDialog, {
        props: { open: true, field: value, fieldTypeOptions: types },
        attachTo: document.body,
    })
    await flushPromises()
}

afterEach(() => wrapper.unmount())

describe('FormEngine/FormFieldDialog', () => {
    it('[FE-CHANGE-07] tipe terkunci saat sudah ada jawaban', async () => {
        await open(field({ answerCount: 4 }))

        expect(wrapper.findComponent(OptionSelect).props('disabled')).toBe(true)
        expect(text()).toContain(
            'Tipe terkunci: sudah ada 4 jawaban. Tambahkan field baru untuk tipe lain; field ini tetap terbaca apa adanya.'
        )
    })

    it('[FE-CHANGE-09] satu jawaban pun mengunci tipe', async () => {
        await open(field({ answerCount: 1 }))

        expect(wrapper.findComponent(OptionSelect).props('disabled')).toBe(true)
        expect(text()).toContain('sudah ada 1 jawaban')
    })

    it('[FE-CHANGE-08] tipe bisa diganti selama belum ada jawaban', async () => {
        await open(field())

        const select = wrapper.findComponent(OptionSelect)
        expect(select.props('disabled')).toBeFalsy()
        expect(text()).not.toContain('Tipe terkunci')
        select.vm.$emit('update:modelValue', '5')
        await flushPromises()
        button('Simpan Field').click()
        await flushPromises()

        expect(text()).toContain('Tambahkan minimal satu opsi')
        expect(wrapper.emitted('save')).toBeUndefined()
    })

    it('[FE-CHANGE-06] hint draft lama saat field menjadi wajib', async () => {
        await open(field())
        expect(text()).not.toContain('draft berjalan tidak dipaksa')

        document.querySelector<HTMLButtonElement>('#field-required')?.click()
        await flushPromises()

        expect(text()).toContain(
            'Berlaku untuk pengajuan yang dimulai setelah disimpan; draft berjalan tidak dipaksa mengisi ulang.'
        )
    })

    it('[FE-CHANGE-11] editor opsi menjelaskan opsi yang dibuang tetap terbaca', async () => {
        await open(field({ fieldTypeId: 5, options: [{ id: 21, label: 'Dasar' }] }))

        expect(text()).toContain(
            'Opsi yang dibuang berhenti ditawarkan untuk jawaban baru; jawaban lama yang memakainya tetap terbaca.'
        )
    })

    it('menolak tipe ber-opsi tanpa opsi', async () => {
        await open(field({ fieldTypeId: 5 }))

        button('Simpan Field').click()
        await flushPromises()

        expect(text()).toContain('Tambahkan minimal satu opsi')
        expect(wrapper.emitted('save')).toBeUndefined()
    })

    it('menolak label kosong', async () => {
        await open(field({ label: '' }))

        button('Simpan Field').click()
        await flushPromises()

        expect(text()).toContain('Label wajib diisi')
        expect(wrapper.emitted('save')).toBeUndefined()
    })

    it('opsi tersimpan yang dikosongkan memblokir simpan, bukan diam-diam diarsipkan', async () => {
        await open(
            field({
                fieldTypeId: 5,
                options: [
                    { id: 21, label: 'Dasar' },
                    { id: 22, label: 'Terapan' },
                ],
            })
        )
        const input = document.querySelector<HTMLInputElement>('input[aria-label="Opsi 2"]')
        if (input === null) throw new Error('opsi tidak ada')
        input.value = '  '
        input.dispatchEvent(new Event('input'))
        await flushPromises()
        button('Simpan Field').click()
        await flushPromises()

        expect(text()).toContain('Label opsi tidak boleh kosong')
        expect(wrapper.emitted('save')).toBeUndefined()
    })

    it('opsi baru yang kosong dibuang saat simpan', async () => {
        await open(field({ fieldTypeId: 5, options: [{ id: 21, label: 'Dasar' }] }))
        button('Tambah Opsi').click()
        await flushPromises()
        button('Simpan Field').click()
        await flushPromises()

        expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
            options: [{ id: 21, label: 'Dasar' }],
        })
    })

    it('field baru: menyimpan label dengan tipe pertama dan tanpa id', async () => {
        await open(null)

        const input = document.querySelector<HTMLInputElement>('#field-label')
        if (input === null) throw new Error('label tidak ada')
        input.value = 'Mitra'
        input.dispatchEvent(new Event('input'))
        await flushPromises()
        button('Simpan Field').click()
        await flushPromises()

        expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({
            id: null,
            label: 'Mitra',
            fieldTypeId: 1,
        })
    })
})
