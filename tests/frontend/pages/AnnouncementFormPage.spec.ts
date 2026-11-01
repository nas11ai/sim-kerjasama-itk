import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

type FakeForm = Record<string, unknown> & { errors: Record<string, string> }

const { submitted } = vi.hoisted(() => ({
    submitted: vi.fn<(method: string, url: string, data: Record<string, unknown>) => void>(),
}))
let errors: Record<string, string> = {}

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        useForm: <T extends object>(data: T) => {
            let transformer = (d: Record<string, unknown>) => d
            const form: FakeForm = reactive({
                ...data,
                errors,
                processing: false,
                transform(fn: (d: Record<string, unknown>) => Record<string, unknown>) {
                    transformer = fn
                    return form
                },
                post(url: string) {
                    const { errors: _e, processing: _p, transform: _t, post: _post, ...rest } = form
                    submitted('post', url, transformer(rest))
                },
            })
            return form
        },
        Head: defineComponent({ setup: () => () => null }),
        Link: defineComponent({
            setup:
                (_, { slots }) =>
                () =>
                    h('a', slots.default?.()),
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
// ProseMirror tidak berjalan andal di jsdom; editor diganti stub yang memancarkan HTML.
vi.mock('@/Components/Announcement/RichTextEditor.vue', () => ({
    default: defineComponent({
        name: 'RichTextEditor',
        props: { modelValue: { type: String, required: true }, id: String, invalid: Boolean },
        emits: ['update:modelValue'],
        setup: () => () => h('div', { class: 'editor-stub' }),
    }),
}))

import AttachmentFields from '@/Components/Announcement/AttachmentFields.vue'
import AudienceFields from '@/Components/Announcement/AudienceFields.vue'
import FormPage from '@/Pages/Announcement/FormPage.vue'
import type { AnnouncementForm, AnnouncementFormPageProps } from '@/types/announcement'

const roleOptions = [
    { value: 'researcher', label: 'Dosen' },
    { value: 'operator', label: 'Operator LPPM' },
]

const existing: AnnouncementForm = {
    id: 8,
    title: 'Format berkas baru',
    content: '<p>Isi</p>',
    type: 'private',
    expiredOn: '2026-12-31',
    targetRoles: ['researcher'],
    files: [{ id: 31, name: 'panduan.pdf', url: '/storage/announcements/x.pdf', size: 2048 }],
}

const route = (name: string, param?: number) =>
    param === undefined ? `/${name}` : `/${name}/${param}`

const mountPage = (announcement: AnnouncementForm | null) => {
    const props: AnnouncementFormPageProps = { announcement, roleOptions }
    return mount(FormPage, { props, global: { mocks: { route } } })
}

const submit = async (wrapper: ReturnType<typeof mountPage>) => {
    await wrapper.find('form').trigger('submit')
    return submitted.mock.calls[0]
}

beforeEach(() => {
    vi.stubGlobal('route', route)
    submitted.mockClear()
    errors = {}
})

describe('Announcement/FormPage', () => {
    it('pilihan peran hanya tampil untuk privat', async () => {
        const wrapper = mountPage(null)
        expect(wrapper.text()).not.toContain('Operator LPPM')

        wrapper.findComponent(AudienceFields).vm.$emit('update:type', 'private')
        await nextTick()

        expect(wrapper.text()).toContain('Operator LPPM')
        expect(wrapper.text()).toContain('Kosongkan untuk semua pengguna login.')
    })

    it('beralih ke publik mengosongkan target peran', async () => {
        const wrapper = mountPage(existing)
        const audience = wrapper.findComponent(AudienceFields)

        audience.vm.$emit('update:type', 'public')
        await nextTick()

        const [, , data] = (await submit(wrapper)) ?? []
        expect(data).toMatchObject({ type: 'public', target_roles: [] })
    })

    it('sunting mengirim POST dengan _method put', async () => {
        const [method, url, data] = (await submit(mountPage(existing))) ?? []

        expect(method).toBe('post')
        expect(url).toBe('/admin.announcements.update/8')
        expect(data).toMatchObject({ _method: 'put', title: 'Format berkas baru' })
    })

    it('buat mengirim ke store tanpa _method', async () => {
        const [, url, data] = (await submit(mountPage(null))) ?? []

        expect(url).toBe('/admin.announcements.store')
        expect(data).not.toHaveProperty('_method')
    })

    it('melepas lampiran lama menambah deleted_files', async () => {
        const wrapper = mountPage(existing)
        const lepas = wrapper.findAll('button').find((b) => b.text() === 'Lepas')

        await lepas?.trigger('click')

        const [, , data] = (await submit(wrapper)) ?? []
        expect(data).toMatchObject({ deleted_files: [31] })
    })

    it('galat files.0 tampil di field lampiran', () => {
        errors = { 'files.0': 'Berkas harus berupa jpg, png, atau pdf.' }
        const wrapper = mountPage(null)

        expect(wrapper.findComponent(AttachmentFields).text()).toContain(
            'Berkas harus berupa jpg, png, atau pdf.'
        )
    })

    it('isi dari editor ikut terkirim sebagai content', async () => {
        const wrapper = mountPage(null)

        wrapper
            .findComponent({ name: 'RichTextEditor' })
            .vm.$emit('update:modelValue', '<p><strong>A</strong></p>')
        await nextTick()

        const [, , data] = (await submit(wrapper)) ?? []
        expect(data).toMatchObject({ content: '<p><strong>A</strong></p>' })
    })

    it('galat content tampil di bawah editor', () => {
        errors = { content: 'Isi pengumuman wajib diisi.' }

        expect(mountPage(null).text()).toContain('Isi pengumuman wajib diisi.')
    })
})
