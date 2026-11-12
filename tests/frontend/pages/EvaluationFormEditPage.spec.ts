import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { router, submitted } = vi.hoisted(() => ({
    router: { post: vi.fn(), delete: vi.fn() },
    submitted: vi.fn<(method: string, url: string, data: unknown) => void>(),
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        router,
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
        Head: defineComponent({ setup: () => () => null }),
        Link: defineComponent({
            props: { href: { type: String, required: true } },
            setup:
                (props, { slots, attrs }) =>
                () =>
                    h('a', { ...attrs, href: props.href }, slots.default?.()),
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

import EvaluationFormEditPage from '@/Pages/FormEngine/EvaluationFormEditPage.vue'
import type { EvaluationFormEditPageData, FieldTypeOption } from '@/types/form-engine'

const types: FieldTypeOption[] = [
    { value: '1', name: 'text', label: 'Teks', hasOptions: false, limitType: 'length' },
]

const form = (overrides: Partial<EvaluationFormEditPageData> = {}): EvaluationFormEditPageData => ({
    id: 9,
    title: 'Lembar Proposal',
    description: null,
    fields: [
        {
            label: 'Kebaruan',
            description: null,
            fieldTypeId: 1,
            isRequired: true,
            limits: { minLength: null, maxLength: null, minValue: null, maxValue: null },
            options: [],
        },
    ],
    hasDraft: true,
    latestVersionLabel: 'Versi 1',
    versions: [{ label: 'Versi 1', publishedAt: '2026-11-01T02:00:00.000Z', publishedBy: 'Admin' }],
    usedBy: [{ phaseId: 4, phaseTitle: 'Penelitian DIPA', stepLabel: 'Pengajuan' }],
    impact: { nextLabel: 'Versi 2', pinnedCohorts: 0 },
    ...overrides,
})

const route = (name: string, params?: number) =>
    params === undefined ? `/${name}` : `/${name}/${params}`

let wrapper: VueWrapper

const mountPage = (data: EvaluationFormEditPageData | null) => {
    wrapper = mount(EvaluationFormEditPage, {
        props: { form: data, fieldTypeOptions: types },
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

const tab = async (label: string) => {
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
})

afterEach(() => {
    wrapper.unmount()
    document.body.innerHTML = ''
})

describe('FormEngine/EvaluationFormEditPage', () => {
    it('banner draf di editor menyebut versi yang masih dipakai reviewer', () => {
        mountPage(form())

        expect(wrapper.text()).toContain('Draf belum diterbitkan. Reviewer masih memakai Versi 1.')
    })

    it('Simpan draf mengirim field ke route draf', () => {
        mountPage(form())

        button('Simpan draf').click()

        expect(submitted).toHaveBeenCalledWith('put', '/admin.evaluation-forms.draft.update/9', {
            fields: [
                {
                    field_type_id: 1,
                    label: 'Kebaruan',
                    description: null,
                    is_required: true,
                    validation_rules: null,
                },
            ],
        })
    })

    it('konfirmasi Terbitkan tanpa angkatan terpaku hanya satu kalimat', async () => {
        mountPage(form())

        button('Terbitkan').click()
        await flushPromises()

        const dialog = document.querySelector('[role="dialog"]')?.textContent ?? ''
        expect(dialog).toContain(
            'Terbitkan Versi 2? Berlaku untuk langkah yang belum mulai dinilai.'
        )
        expect(dialog).not.toContain('angkatan')
    })

    it('konfirmasi Terbitkan menyebut angkatan yang tetap memakai versi lama', async () => {
        mountPage(form({ impact: { nextLabel: 'Versi 2', pinnedCohorts: 2 } }))

        button('Terbitkan').click()
        await flushPromises()

        expect(document.querySelector('[role="dialog"]')?.textContent).toContain(
            'Terbitkan Versi 2? Berlaku untuk langkah yang belum mulai dinilai. 2 angkatan sudah memakai versi lama dan tetap memakainya.'
        )
        button('Terbitkan Versi 2').click()
        expect(router.post).toHaveBeenCalledWith(
            '/admin.evaluation-forms.publish/9',
            {},
            expect.any(Object)
        )
    })

    it('Pratinjau merender field draf seperti lembar reviewer, terkunci', async () => {
        mountPage(form())

        await tab('Pratinjau')

        const preview = document.querySelector('[data-preview]')
        expect(preview?.textContent).toContain('Kebaruan')
        expect(preview?.querySelector('fieldset')?.disabled).toBe(true)
        expect(preview?.textContent).not.toContain('Submit Evaluasi')
    })

    it('Versi & pemakaian mendaftar riwayat versi dan tahap pemakainya', async () => {
        mountPage(form())

        await tab('Versi & pemakaian')

        expect(document.body.textContent).toContain('Versi 1')
        expect(document.body.textContent).toContain('Penelitian DIPA')
        expect(document.querySelector('a[href="/admin.phases.edit/4"]')).not.toBeNull()
    })

    it('mode buat hanya meminta judul dan deskripsi', () => {
        mountPage(null)

        expect(wrapper.text()).toContain('Buat Form Evaluasi')
        expect(document.querySelector('[role="tab"]')).toBeNull()
        button('Simpan').click()
        expect(submitted).toHaveBeenCalledWith('post', '/admin.evaluation-forms.store', {
            title: '',
            description: null,
        })
    })
})
