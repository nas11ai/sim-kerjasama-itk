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
import type {
    FormEditPageProps,
    FormEditPhasesProps,
    FormGeneralData,
    PhaseCardData,
} from '@/types/form-engine'

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

const phaseCard = (overrides: Partial<PhaseCardData> = {}): PhaseCardData => ({
    id: 1,
    title: 'Pengajuan Proposal',
    description: null,
    isActive: true,
    detailCount: 2,
    periodNames: ['Penelitian DIPA 2026'],
    submissionCount: 0,
    linkablePeriods: [{ value: '9', label: '2026 Gelombang 2' }],
    deadlineGaps: [],
    ...overrides,
})

const phases = (overrides: Partial<FormEditPhasesProps> = {}): FormEditPhasesProps => ({
    tab: 'phases',
    form: { id: 7, title: 'Form Proposal Penelitian', isActive: true },
    can: { linkPeriod: true },
    phases: [
        phaseCard(),
        phaseCard({
            id: 2,
            title: 'Tahap Lama',
            description: 'Sudah tidak dipakai',
            isActive: false,
            detailCount: 1,
            periodNames: [],
            submissionCount: 4,
        }),
    ],
    ...overrides,
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

const statusBadge = (wrapper: ReturnType<typeof mountPage>, index: number) =>
    wrapper
        .findAll('article')
        [index].findAll('*')
        .find((el) => el.text() === 'Nonaktif')

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

    it('mode sunting: judul, badge status, tab Phases menaut ke tahap', () => {
        const wrapper = mountPage(editing())

        expect(wrapper.find('h1').text()).toBe('Edit Form — Form Proposal Penelitian')
        expect(heading(wrapper)).toContain('Nonaktif')
        expect(tab(wrapper, 'General & Fields')?.attributes('href')).toBe('/admin.forms.edit/7')
        expect(tab(wrapper, 'Phases')?.attributes('href')).toBe('/admin.forms.phases.index/7')
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

    describe('tab Phases', () => {
        it('tab aktif, subjudul, dan aksi header menaut ke layar buat', () => {
            const wrapper = mountPage(phases())

            expect(tab(wrapper, 'Phases')?.attributes('aria-current')).toBe('page')
            expect(wrapper.text()).toContain('Phases & Deadlines')
            expect(wrapper.find('a[href^="/admin.forms.phases.create"]').text()).toContain(
                'Buat Tahap Baru'
            )
        })

        it('kartu menampilkan jumlah tahapan, periode, status, dan aksi', () => {
            const wrapper = mountPage(phases())

            expect(wrapper.text()).toContain('2 tahapan · Periode: Penelitian DIPA 2026')
            expect(wrapper.text()).toContain('1 tahapan · Belum terhubung')
            expect(statusBadge(wrapper, 0)).toBeUndefined()
            expect(statusBadge(wrapper, 1)).toBeDefined()
            expect(wrapper.findAll('article')[1].text()).toContain('Aktifkan')
            expect(wrapper.findAll('article')[0].text()).toContain('Hubungkan ke Periode')
            expect(wrapper.find('a[href="/admin.forms.phases.edit/7/1"]').text()).toContain('Edit')
        })

        it('tombol Hubungkan ke Periode tersembunyi tanpa periods.manage', () => {
            const wrapper = mountPage(phases({ can: { linkPeriod: false } }))

            expect(wrapper.text()).not.toContain('Hubungkan ke Periode')
        })

        it('kosong: pesan dan tombol selesai menaut ke Daftar Form', () => {
            const wrapper = mountPage(phases({ phases: [] }))

            expect(wrapper.text()).toContain('Belum ada tahap yang memakai form ini.')
            expect(wrapper.find('a[href="/admin.forms.index"]').exists()).toBe(true)
        })

        it('Selesai — ke Daftar Form menaut admin.forms.index', () => {
            const wrapper = mountPage(phases())
            const done = wrapper.findAll('a').find((a) => a.text() === 'Selesai — ke Daftar Form')

            expect(done?.attributes('href')).toBe('/admin.forms.index')
        })

        const bodyButton = (label: string) =>
            Array.from(document.querySelectorAll('button')).find(
                (b) => b.textContent?.trim() === label
            )

        it('[FE-DELETE-06] hapus tahap berpengajuan membuka penjelasan berangka, bukan konfirmasi', async () => {
            const wrapper = mountPage(phases())
            await wrapper.findAll('article')[1].findAll('button').at(-1)?.trigger('click')
            await flushPromises()

            expect(document.body.textContent).toContain('Tahap tidak bisa dihapus')
            expect(document.body.textContent).toContain('Tahap ini dirujuk 4 pengajuan')
            expect(document.body.textContent).not.toContain('Hapus tahap?')
            expect(router.delete).not.toHaveBeenCalled()
        })

        it('penjelasan menawarkan Nonaktifkan Tahap hanya bila tahap masih aktif', async () => {
            const wrapper = mountPage(phases({ phases: [phaseCard({ submissionCount: 2 })] }))
            await clickButton(wrapper, 'Hapus')

            expect(bodyButton('Nonaktifkan Tahap')).toBeDefined()
        })

        it('[FE-DELETE-09] tahap kosong dikonfirmasi lalu dihapus', async () => {
            const wrapper = mountPage(phases())
            await clickButton(wrapper, 'Hapus')

            expect(document.body.textContent).toContain('Hapus tahap?')
            expect(document.body.textContent).toContain(
                'Pengajuan Proposal beserta 2 detail dan form evaluasinya dihapus permanen.'
            )
            bodyButton('Hapus Tahap')?.click()
            await flushPromises()
            expect(router.delete).toHaveBeenCalledWith(
                '/admin.forms.phases.destroy/7/1',
                expect.anything()
            )
        })

        it('nonaktifkan dikonfirmasi lalu memanggil route active', async () => {
            const wrapper = mountPage(phases())
            await clickButton(wrapper, 'Nonaktifkan')

            expect(document.body.textContent).toContain('Nonaktifkan tahap?')
            bodyButton('Nonaktifkan Tahap')?.click()
            await flushPromises()
            expect(router.patch).toHaveBeenCalledWith(
                '/admin.forms.phases.active/7/1',
                { active: false },
                expect.anything()
            )
        })

        it('mengaktifkan tahap nonaktif memanggil route active dengan true', async () => {
            const wrapper = mountPage(phases({ phases: [phaseCard({ isActive: false })] }))
            await clickButton(wrapper, 'Aktifkan')

            expect(document.body.textContent).toContain('Aktifkan tahap?')
            bodyButton('Aktifkan Tahap')?.click()
            await flushPromises()
            expect(router.patch).toHaveBeenCalledWith(
                '/admin.forms.phases.active/7/1',
                { active: true },
                expect.anything()
            )
        })
    })
})
