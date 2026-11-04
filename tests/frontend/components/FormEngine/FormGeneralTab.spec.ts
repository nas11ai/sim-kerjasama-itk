import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { submitted, serverErrors } = vi.hoisted(() => ({
    submitted: vi.fn<(method: string, url: string, data: Record<string, unknown>) => void>(),
    serverErrors: { value: {} as Record<string, string> },
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        useForm: <T extends object>(data: T) => {
            const form = reactive({
                ...data,
                errors: serverErrors.value,
                processing: false,
                put(url: string) {
                    submitted('put', url, { ...data, ...form })
                },
                post(url: string) {
                    submitted('post', url, { ...data, ...form })
                },
            })
            return form
        },
    }
})

import FormGeneralTab from '@/Components/FormEngine/FormGeneralTab.vue'
import type { FormGeneralData } from '@/types/form-engine'

const route = (name: string, id?: number) => (id === undefined ? `/${name}` : `/${name}/${id}`)

const general = (overrides: Partial<FormGeneralData> = {}): FormGeneralData => ({
    title: 'Form Proposal',
    description: null,
    formTypeId: 2,
    isActive: true,
    fields: [
        {
            id: 11,
            label: 'Judul Penelitian',
            fieldTypeId: 1,
            isRequired: true,
            requiredSince: '2025-12-01T00:00:00+08:00',
            answerCount: 3,
            options: [],
        },
        {
            id: 12,
            label: 'Abstrak',
            fieldTypeId: 1,
            isRequired: false,
            requiredSince: null,
            answerCount: 0,
            options: [],
        },
    ],
    archivedFields: [{ id: 13, label: 'Lokasi (lama)', typeName: 'Teks' }],
    affectedDrafts: 0,
    ...overrides,
})

const mountTab = (overrides: Partial<FormGeneralData> = {}) =>
    mount(FormGeneralTab, {
        props: {
            form: { id: 7, title: 'Form Proposal', isActive: true },
            general: general(overrides),
            fieldTypeOptions: [
                { value: '1', label: 'Teks', hasOptions: false, limitKind: 'length' },
            ],
            formTypeOptions: [{ value: '2', label: 'Penelitian' }],
        },
        attachTo: document.body,
        global: { mocks: { route } },
    })

const clickButton = async (wrapper: ReturnType<typeof mountTab>, label: string) => {
    await wrapper
        .findAll('button')
        .find((b) => b.text().trim() === label)
        ?.trigger('click')
    await flushPromises()
}

beforeEach(() => {
    vi.stubGlobal('route', route)
    submitted.mockClear()
    serverErrors.value = {}
})

describe('FormEngine/FormGeneralTab', () => {
    it('[FE-CHANGE-04] tanpa draft berjalan tidak ada peringatan maupun tawaran notifikasi', () => {
        const wrapper = mountTab()

        expect(wrapper.text()).not.toContain('draft berjalan akan terdampak')
        expect(wrapper.text()).not.toContain('Kirim notifikasi')
    })

    it('[FE-CHANGE-02] menyebut jumlah peneliti terdampak dan menawarkan notifikasi', async () => {
        const wrapper = mountTab({ affectedDrafts: 3 })

        expect(wrapper.text()).toContain(
            '3 peneliti dengan draft berjalan akan terdampak perubahan ini. Centang untuk mengirimkan pemberitahuan.'
        )
        expect(wrapper.text()).toContain('Kirim notifikasi ke 3 peneliti terdampak')

        await wrapper.find('#notify-affected').trigger('click')
        await clickButton(wrapper, 'Simpan')

        expect(submitted.mock.calls[0]?.[2]).toMatchObject({ notify_affected: true })
    })

    it('[FE-CHANGE-01] field terarsip tampil read-only', () => {
        const wrapper = mountTab()

        expect(wrapper.text()).toContain('Lokasi (lama)')
        expect(wrapper.text()).toContain('Diarsipkan')
        expect(wrapper.findAll('button[aria-label="Edit Lokasi (lama)"]')).toHaveLength(0)
    })

    it('Simpan mengirim update; Lanjut mengirim continue', async () => {
        const wrapper = mountTab()

        await wrapper.find('form').trigger('submit')
        await clickButton(wrapper, 'Lanjut: Access Control')

        expect(submitted.mock.calls[0]).toMatchObject([
            'put',
            '/admin.forms.update/7',
            { continue: false },
        ])
        expect(submitted.mock.calls[1]?.[2]).toMatchObject({ continue: true })
    })

    it('Naik mengubah urutan kiriman dan field tetap membawa id', async () => {
        const wrapper = mountTab()

        await wrapper.find('button[aria-label="Naik: Abstrak"]').trigger('click')
        await wrapper.find('form').trigger('submit')

        expect(submitted.mock.calls[0]?.[2].fields).toEqual([
            { id: 12, field_type_id: 1, label: 'Abstrak', is_required: false },
            { id: 11, field_type_id: 1, label: 'Judul Penelitian', is_required: true },
        ])
    })

    it('Hapus field tersimpan meminta konfirmasi lalu mengarsipkan', async () => {
        const wrapper = mountTab()

        await wrapper.find('button[aria-label="Hapus Abstrak"]').trigger('click')
        await flushPromises()
        expect(document.body.textContent).toContain('Hapus field?')
        expect(document.body.textContent).toContain('Abstrak diarsipkan, bukan dihapus permanen.')

        const confirm = Array.from(document.querySelectorAll('[role="dialog"] button')).find(
            (b) => b.textContent?.trim() === 'Hapus'
        )
        confirm?.click()
        await flushPromises()
        await wrapper.find('form').trigger('submit')

        expect(submitted.mock.calls[0]?.[2].fields).toEqual([
            { id: 11, field_type_id: 1, label: 'Judul Penelitian', is_required: true },
        ])
    })

    it('galat baris tampil di baris, galat lain di banner', () => {
        serverErrors.value = {
            'fields.1.label': 'Label wajib diisi.',
            field_type_id: 'Tipe field terkunci.',
        }
        const wrapper = mountTab()

        const rows = wrapper.findAll('li')
        expect(rows[1]?.text()).toContain('Label wajib diisi.')
        expect(rows[0]?.text()).not.toContain('Label wajib diisi.')
        expect(wrapper.find('[role="alert"]').text()).toContain('Tipe field terkunci.')
    })
})
