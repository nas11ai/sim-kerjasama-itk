import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { submitted } = vi.hoisted(() => ({
    submitted: vi.fn<(method: string, url: string, data: Record<string, unknown>) => void>(),
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        useForm: <T extends object>(data: T) => {
            const form = reactive({
                ...data,
                errors: {},
                processing: false,
                post(url: string) {
                    submitted('post', url, { ...data, ...form })
                },
                put(url: string) {
                    submitted('put', url, { ...data, ...form })
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
    fields: [],
    archivedFields: [],
    affectedDrafts: 0,
    ...overrides,
})

const props = (overrides: Partial<FormEditPageProps> = {}): FormEditPageProps => ({
    tab: 'general',
    form: null,
    general: general(),
    fieldTypeOptions: [{ value: '1', label: 'Teks', hasOptions: false }],
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
            archivedFields: [{ id: 12, label: 'Lokasi Penelitian (lama)', typeName: 'text' }],
        }),
    })

const route = (name: string, id?: number) => (id === undefined ? `/${name}` : `/${name}/${id}`)

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

    it('mode sunting: judul, badge status, tab Phases menaut ke tahap', () => {
        const wrapper = mountPage(editing())

        expect(wrapper.find('h1').text()).toBe('Edit Form — Form Proposal Penelitian')
        expect(heading(wrapper)).toContain('Nonaktif')
        expect(tab(wrapper, 'General & Fields')?.attributes('href')).toBe('/admin.forms.edit/7')
        expect(tab(wrapper, 'Phases')?.attributes('href')).toBe('/admin.form-phases.index')
        expect(wrapper.find('a[href="/admin.forms.index"]').text()).toContain('Form Builder')
    })

    it('[FE-CHANGE-01] field aktif dan field terarsip terbaca', () => {
        const wrapper = mountPage(editing())

        expect(wrapper.text()).toContain('Judul Penelitian')
        expect(wrapper.text()).toContain('Wajib')
        expect(wrapper.text()).toContain('Lokasi Penelitian (lama)')
        expect(wrapper.text()).toContain('Diarsipkan')
    })

    it('mode buat: Simpan mengirim store tanpa tombol Lanjut', async () => {
        const wrapper = mountPage(props())

        expect(wrapper.findAll('button').some((b) => b.text().includes('Lanjut'))).toBe(false)
        expect(wrapper.find('button[type="submit"]').text()).toBe('Simpan')
        await wrapper.find('form').trigger('submit')

        expect(submitted).toHaveBeenCalledWith(
            'post',
            '/admin.forms.store',
            expect.objectContaining({ continue: false, fields: [] })
        )
    })

    it('mode sunting: field lama dikirim ulang dengan id, tanpa kunci options', async () => {
        const wrapper = mountPage(editing())

        await clickButton(wrapper, 'Lanjut: Access Control')

        const [method, url, data] = submitted.mock.calls[0] ?? []
        expect([method, url]).toEqual(['put', '/admin.forms.update/7'])
        expect(data).toMatchObject({ continue: true, is_active: false, form_type_id: '2' })
        expect(data?.fields).toEqual([
            { id: 11, field_type_id: 1, label: 'Judul Penelitian', is_required: true },
        ])
    })
})
