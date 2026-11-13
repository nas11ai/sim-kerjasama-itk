import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { submitted, serverErrors, router } = vi.hoisted(() => ({
    submitted: vi.fn<(method: string, url: string, data: Record<string, unknown>) => void>(),
    serverErrors: { value: {} as Record<string, string> },
    router: { post: vi.fn(), delete: vi.fn() },
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        router,
        useForm: <T extends object>(data: T) => {
            let transformer = (value: T): Record<string, unknown> => ({ ...value })
            const form = reactive({
                ...data,
                errors: serverErrors.value,
                processing: false,
                isDirty: false,
                defaults() {},
                transform(fn: (value: T) => Record<string, unknown>) {
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

import FormGeneralTab from '@/Components/FormEngine/FormGeneralTab.vue'
import type { FormFieldEditData, FormGeneralData } from '@/types/form-engine'

const route = (name: string, id?: number) => (id === undefined ? `/${name}` : `/${name}/${id}`)

const judul: FormFieldEditData = {
    id: 11,
    label: 'Judul Penelitian',
    fieldTypeId: 1,
    isRequired: true,
    requiredSince: '2025-12-01T00:00:00+08:00',
    answerCount: 3,
    options: [],
}
const abstrak: FormFieldEditData = {
    id: 12,
    label: 'Abstrak',
    fieldTypeId: 1,
    isRequired: false,
    requiredSince: null,
    answerCount: 0,
    options: [],
}

const general = (overrides: Partial<FormGeneralData> = {}): FormGeneralData => ({
    title: 'Form Proposal',
    description: null,
    formTypeId: 2,
    isActive: true,
    hasDraft: false,
    publishedAt: '2026-01-01T00:00:00+08:00',
    fields: [judul, abstrak],
    publishedFields: [judul, abstrak],
    archivedFields: [{ id: 13, label: 'Lokasi (lama)', typeName: 'Teks' }],
    impact: { newRequired: 0, draftOwners: 0, relabelled: [], removed: 0 },
    ...overrides,
})

const mountTab = (overrides: Partial<FormGeneralData> = {}, creating = false) =>
    mount(FormGeneralTab, {
        props: {
            form: creating ? null : { id: 7, title: 'Form Proposal', isActive: true },
            general: general(overrides),
            fieldTypeOptions: [
                { value: '1', name: 'text', label: 'Teks', hasOptions: false, limitType: 'length' },
            ],
            formTypeOptions: [{ value: '2', label: 'Penelitian' }],
        },
        attachTo: document.body,
        global: { mocks: { route } },
    })

let mounted: ReturnType<typeof mountTab> | null = null
const tab = (overrides: Partial<FormGeneralData> = {}, creating = false) =>
    (mounted = mountTab(overrides, creating))

const button = (label: string) => {
    const found = Array.from(document.querySelectorAll('button')).find(
        (b) => b.textContent?.trim() === label
    )
    if (found === undefined) throw new Error(`tombol "${label}" tidak ada`)
    return found
}
const click = async (label: string) => {
    button(label).click()
    await flushPromises()
}
const dialogText = () => document.querySelector('[role="dialog"]')?.textContent ?? ''
const openTab = async (label: string) => {
    const trigger = Array.from(document.querySelectorAll('[role="tab"]')).find(
        (t) => t.textContent?.trim() === label
    )
    if (!(trigger instanceof HTMLElement)) throw new Error(`tab "${label}" tidak ada`)
    trigger.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))
    trigger.focus()
    await flushPromises()
}

beforeEach(() => {
    vi.stubGlobal('route', route)
    submitted.mockClear()
    router.post.mockClear()
    serverErrors.value = {}
})

afterEach(() => {
    mounted?.unmount()
    mounted = null
    document.body.innerHTML = ''
})

describe('FormEngine/FormGeneralTab', () => {
    it('mode buat mengirim General beserta field sebagai draf pertama', async () => {
        tab({ fields: [], publishedFields: [], archivedFields: [], publishedAt: null }, true)

        await click('Buat Form')

        expect(submitted.mock.calls[0]).toMatchObject([
            'post',
            '/admin.forms.store',
            { title: 'Form Proposal', fields: [] },
        ])
        expect(document.body.textContent).not.toContain('Simpan draf')
    })

    it('Simpan hanya mengirim General; Lanjut mengirim continue', async () => {
        tab()

        await click('Simpan')
        await click('Lanjut: Access Control')

        expect(submitted.mock.calls[0]).toMatchObject([
            'put',
            '/admin.forms.update/7',
            { continue: false },
        ])
        expect(submitted.mock.calls[0]?.[2]).not.toHaveProperty('fields')
        expect(submitted.mock.calls[1]?.[2]).toMatchObject({ continue: true })
    })

    it('Simpan draf mengirim field urut beserta id-nya ke route draf', async () => {
        const wrapper = tab()

        await wrapper.find('button[aria-label="Naik: Abstrak"]').trigger('click')
        await click('Simpan draf')

        expect(submitted.mock.calls[0]).toEqual([
            'put',
            '/admin.forms.draft.update/7',
            {
                fields: [
                    { id: 12, field_type_id: 1, label: 'Abstrak', is_required: false },
                    { id: 11, field_type_id: 1, label: 'Judul Penelitian', is_required: true },
                ],
            },
        ])
    })

    it('Hapus field tersimpan meminta konfirmasi lalu dibuang dari draf', async () => {
        const wrapper = tab()

        await wrapper.find('button[aria-label="Hapus Abstrak"]').trigger('click')
        await flushPromises()
        expect(dialogText()).toContain('Abstrak diarsipkan, bukan dihapus permanen.')

        const confirm = Array.from(document.querySelectorAll('[role="dialog"] button')).find(
            (b) => b.textContent?.trim() === 'Hapus'
        )
        ;(confirm as HTMLElement | undefined)?.click()
        await flushPromises()
        await click('Simpan draf')

        expect(submitted.mock.calls[0]?.[2]).toEqual({
            fields: [{ id: 11, field_type_id: 1, label: 'Judul Penelitian', is_required: true }],
        })
    })

    it('[FE-CHANGE-01] field terarsip tampil read-only', () => {
        const wrapper = tab()

        expect(wrapper.text()).toContain('Lokasi (lama)')
        expect(wrapper.text()).toContain('Diarsipkan')
        expect(wrapper.findAll('button[aria-label="Edit Lokasi (lama)"]')).toHaveLength(0)
    })

    it('banner draf Form yang sudah terbit', () => {
        const wrapper = tab({ hasDraft: true })

        expect(wrapper.text()).toContain(
            'Draf belum diterbitkan. Peneliti masih melihat field terbitan terakhir.'
        )
    })

    it('banner Form yang belum pernah terbit, juga sesudah drafnya dibuang', () => {
        const wrapper = tab({ hasDraft: false, publishedAt: null })

        expect(wrapper.text()).toContain(
            'Draf belum diterbitkan. Form ini belum bisa dipakai tahap.'
        )
    })

    it('tanpa draf tidak ada banner dan Terbitkan nonaktif', () => {
        const wrapper = tab()

        expect(wrapper.text()).not.toContain('Draf belum diterbitkan')
        expect(button('Terbitkan').disabled).toBe(true)
    })

    it('[FE-FORMPUB-02] konfirmasi Terbitkan menyebut setiap butir dampak, berurutan', async () => {
        tab({
            hasDraft: true,
            impact: {
                newRequired: 1,
                draftOwners: 2,
                relabelled: [{ label: 'Judul', answered: 3 }],
                removed: 1,
            },
        })

        await click('Terbitkan')

        expect(dialogText()).toContain(
            '1 field baru wajib, berlaku untuk pengajuan yang dibuat sesudah ini. 2 peneliti dengan draf berjalan tidak terpengaruh. Label "Judul" diubah pada field yang sudah dijawab 3 pengajuan. 1 field dihapus, jawaban lamanya tetap tersimpan.'
        )
    })

    it('[FE-CHANGE-04] tanpa butir dampak: satu kalimat, tanpa tawaran notifikasi', async () => {
        tab({ hasDraft: true })

        await click('Terbitkan')

        expect(dialogText()).toContain('Terbitkan perubahan Form ini?')
        expect(dialogText()).not.toContain('Kirim notifikasi')
    })

    it('[FE-CHANGE-02] dialog Terbitkan menawarkan notifikasi ke pemilik draft berjalan', async () => {
        tab({
            hasDraft: true,
            impact: { newRequired: 0, draftOwners: 3, relabelled: [], removed: 0 },
        })

        await click('Terbitkan')
        expect(dialogText()).toContain('Kirim notifikasi ke 3 peneliti terdampak')
        ;(document.querySelector('#notify-affected') as HTMLElement).click()
        await flushPromises()
        const confirm = Array.from(document.querySelectorAll('[role="dialog"] button')).find(
            (b) => b.textContent?.trim() === 'Terbitkan'
        )
        ;(confirm as HTMLElement | undefined)?.click()
        await flushPromises()

        expect(router.post).toHaveBeenCalledWith(
            '/admin.forms.publish/7',
            { notify_affected: true },
            expect.any(Object)
        )
    })

    it('Pratinjau merender field editor seperti isian peneliti (G3-W8)', async () => {
        tab({ hasDraft: true })

        await openTab('Pratinjau')

        const preview = document.querySelector('[data-preview]')
        expect(preview?.textContent).toContain('Judul Penelitian')
        expect(preview?.querySelector('#field-11')).not.toBeNull()
    })

    it('galat baris tampil di baris, galat lain di banner', () => {
        serverErrors.value = {
            'fields.1.label': 'Label wajib diisi.',
            field_type_id: 'Tipe field terkunci.',
        }
        const wrapper = tab()

        const rows = wrapper.findAll('li')
        expect(rows[1]?.text()).toContain('Label wajib diisi.')
        expect(rows[0]?.text()).not.toContain('Label wajib diisi.')
        expect(wrapper.text()).toContain('Tipe field terkunci.')
    })
})
