import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { submitted, router } = vi.hoisted(() => ({
    submitted: vi.fn<(method: string, url: string, data: Record<string, unknown>) => void>(),
    router: { patch: vi.fn(), delete: vi.fn() },
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        router,
        useForm: <T extends object>(data: T) => {
            let transformer = (value: T): Record<string, unknown> => ({ ...value })
            const form = reactive({
                ...data,
                errors: {},
                processing: false,
                isDirty: false,
                defaults() {},
                transform(fn: (value: T) => Record<string, unknown>) {
                    transformer = fn
                    return form
                },
                post(url: string) {
                    submitted('post', url, transformer({ ...data, ...form }))
                },
                put(url: string) {
                    submitted('put', url, transformer({ ...data, ...form }))
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

import FormEditPage from '@/Pages/FormEngine/FormEditPage.vue'
import type { FormEditPageProps, FormGeneralData } from '@/types/form-engine'

const general = (overrides: Partial<FormGeneralData> = {}): FormGeneralData => ({
    title: '',
    description: null,
    formTypeId: null,
    isActive: true,
    hasDraft: false,
    publishedAt: null,
    fields: [],
    publishedFields: [],
    archivedFields: [],
    impact: { newRequired: 0, draftOwners: 0, relabelled: [], removed: 0 },
    ...overrides,
})

const props = (overrides: Partial<FormEditPageProps> = {}): FormEditPageProps => ({
    tab: 'general',
    form: null,
    general: general(),
    fieldTypeOptions: [{ value: '1', label: 'Teks', hasOptions: false, limitType: 'length' }],
    formTypeOptions: [{ value: '2', label: 'Penelitian' }],
    ...overrides,
})

const editing = (): FormEditPageProps =>
    props({
        form: { id: 7, title: 'Form Proposal Penelitian', isActive: false },
        general: general({
            title: 'Form Proposal Penelitian',
            formTypeId: 2,
            isActive: false,
            publishedAt: '2026-01-01T00:00:00+08:00',
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
            ],
            archivedFields: [{ id: 12, label: 'Lokasi Penelitian (lama)', typeName: 'Teks' }],
        }),
    })

const route = (name: string, params?: number | Record<string, number>) => {
    if (params === undefined) return `/${name}`
    const ids = typeof params === 'number' ? [params] : Object.values(params)
    return `/${name}/${ids.join('/')}`
}

const mountPage = (pageProps: FormEditPageProps) =>
    mount(FormEditPage, { props: pageProps, global: { mocks: { route } } })

const heading = (wrapper: ReturnType<typeof mountPage>) =>
    wrapper.find('h1').element.parentElement?.textContent ?? ''

const tab = (wrapper: ReturnType<typeof mountPage>, label: string) =>
    wrapper.findAll('nav a, nav span').find((el) => el.text().trim() === label)

const clickButton = async (wrapper: ReturnType<typeof mountPage>, label: string) => {
    await wrapper
        .findAll('button')
        .find((b) => b.text().trim() === label)
        ?.trigger('click')
    await flushPromises()
}

beforeEach(() => {
    vi.stubGlobal('route', route)
    submitted.mockClear()
    document.body.innerHTML = ''
    Object.values(router).forEach((fn) => fn.mockClear())
})

describe('FormEngine/FormEditPage', () => {
    it('mode buat: judul, tanpa badge, tab Access Control dan Phases disabled', () => {
        const wrapper = mountPage(props())

        expect(wrapper.find('h1').text()).toBe('Buat Form Baru')
        expect(heading(wrapper)).not.toContain('Aktif')
        for (const label of ['Access Control', 'Phases']) {
            const el = tab(wrapper, label)
            expect(el?.element.tagName).toBe('SPAN')
            expect(el?.attributes('aria-disabled')).toBe('true')
            expect(el?.attributes('title')).toBe('Simpan form lebih dulu')
        }
        expect(tab(wrapper, 'General & Fields')?.attributes('aria-current')).toBe('page')
    })

    it('mode sunting: judul, badge status, tab Phases menaut ke pustaka bertapis (R2-W4)', () => {
        const wrapper = mountPage(editing())

        expect(wrapper.find('h1').text()).toBe('Edit Form: Form Proposal Penelitian')
        expect(heading(wrapper)).toContain('Nonaktif')
        expect(tab(wrapper, 'General & Fields')?.attributes('href')).toBe('/admin.forms.edit/7')
        expect(tab(wrapper, 'Phases')?.attributes('href')).toBe('/admin.phases.index/7')
        expect(tab(wrapper, 'Phases')?.attributes('aria-current')).toBeUndefined()
        expect(wrapper.find('a[href="/admin.forms.index"]').text()).toContain('Pembuat Formulir')
    })

    it('[FE-CHANGE-01] field aktif dan field terarsip terbaca', () => {
        const wrapper = mountPage(editing())

        expect(wrapper.text()).toContain('Judul Penelitian')
        expect(wrapper.text()).toContain('Wajib')
        expect(wrapper.text()).toContain('Lokasi Penelitian (lama)')
        expect(wrapper.text()).toContain('Diarsipkan')
    })

    it('mode buat: Buat Form mengirim store tanpa tombol Lanjut', async () => {
        const wrapper = mountPage(props())

        expect(wrapper.findAll('button').some((b) => b.text().includes('Lanjut'))).toBe(false)
        expect(wrapper.find('button[type="submit"]').text()).toBe('Buat Form')
        await wrapper.find('form').trigger('submit')

        expect(submitted).toHaveBeenCalledWith(
            'post',
            '/admin.forms.store',
            expect.objectContaining({ continue: false, fields: [] })
        )
    })

    it('mode sunting: Lanjut menyimpan General saja; field lama ke draf dengan id, tanpa kunci options', async () => {
        const wrapper = mountPage(editing())

        await clickButton(wrapper, 'Lanjut: Access Control')
        await clickButton(wrapper, 'Simpan draf')

        const [method, url, data] = submitted.mock.calls[0] ?? []
        expect([method, url]).toEqual(['put', '/admin.forms.update/7'])
        expect(data).toMatchObject({ continue: true, is_active: false, form_type_id: '2' })
        expect(data).not.toHaveProperty('fields')
        expect(submitted.mock.calls[1]).toEqual([
            'put',
            '/admin.forms.draft.update/7',
            {
                fields: [
                    { id: 11, field_type_id: 1, label: 'Judul Penelitian', is_required: true },
                ],
            },
        ])
    })
})
