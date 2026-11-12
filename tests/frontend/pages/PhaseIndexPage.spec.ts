import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router } = vi.hoisted(() => ({
    router: { patch: vi.fn(), delete: vi.fn() },
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        router,
        useForm: <T extends object>(data: T) =>
            reactive({
                ...data,
                errors: {},
                processing: false,
                reset() {},
                clearErrors() {},
                post() {},
            }),
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

import PhaseIndexPage from '@/Pages/FormEngine/PhaseIndexPage.vue'
import type { PhaseCardData, PhaseIndexPageProps } from '@/types/form-engine'

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
    submissionFormTitle: 'Form Proposal Penelitian',
    versionLabel: 'Versi 1',
    hasDraft: false,
    ...overrides,
})

const props = (overrides: Partial<PhaseIndexPageProps> = {}): PhaseIndexPageProps => ({
    filterForm: null,
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
            submissionFormTitle: 'Form Pengmas',
        }),
    ],
    ...overrides,
})

const route = (name: string, params?: number | Record<string, number>) => {
    if (params === undefined) return `/${name}`
    const ids = typeof params === 'number' ? [params] : Object.values(params)
    return `/${name}/${ids.join('/')}`
}

const mountPage = (pageProps: PhaseIndexPageProps) =>
    mount(PhaseIndexPage, { props: pageProps, global: { mocks: { route } } })

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

const bodyButton = (label: string) =>
    Array.from(document.querySelectorAll('button')).find((b) => b.textContent?.trim() === label)

beforeEach(() => {
    vi.stubGlobal('route', route)
    document.body.innerHTML = ''
    Object.values(router).forEach((fn) => fn.mockClear())
})

describe('FormEngine/PhaseIndexPage', () => {
    it('judul, subjudul, dan aksi header menaut ke layar buat', () => {
        const wrapper = mountPage(props())

        expect(wrapper.find('h1').text()).toBe('Fase')
        expect(wrapper.text()).toContain('Pustaka tahap yang bisa dipakai banyak periode.')
        expect(wrapper.find('a[href="/admin.phases.create"]').text()).toContain('Buat Tahap Baru')
    })

    it('kartu menampilkan Form pengajuan, jumlah tahapan, periode, status, dan aksi', () => {
        const wrapper = mountPage(props())
        const cards = wrapper.findAll('article')

        expect(cards[0].text()).toContain('Form pengajuan: Form Proposal Penelitian')
        expect(cards[1].text()).toContain('Form pengajuan: Form Pengmas')
        expect(wrapper.text()).toContain('2 tahapan · Periode: Penelitian DIPA 2026')
        expect(wrapper.text()).toContain('1 tahapan · Belum terhubung')
        expect(statusBadge(wrapper, 0)).toBeUndefined()
        expect(statusBadge(wrapper, 1)).toBeDefined()
        expect(cards[1].text()).toContain('Aktifkan')
        expect(cards[0].text()).toContain('Hubungkan ke Periode')
        expect(wrapper.find('a[href="/admin.phases.edit/1"]').text()).toContain('Edit')
    })

    // BR-FE-33 — tahap tanpa versi belum bisa dipakai periode.
    it('kartu tahap yang belum pernah terbit berlencana Belum terbit', () => {
        const wrapper = mountPage(
            props({
                phases: [phaseCard({ versionLabel: null, hasDraft: true, linkablePeriods: [] })],
            })
        )

        expect(wrapper.findAll('article')[0].text()).toContain('Belum terbit')
        expect(wrapper.findAll('article')[0].text()).not.toContain('Hubungkan ke Periode')
    })

    it('tombol Hubungkan ke Periode tersembunyi tanpa periods.manage', () => {
        const wrapper = mountPage(props({ can: { linkPeriod: false } }))

        expect(wrapper.text()).not.toContain('Hubungkan ke Periode')
    })

    it('tanpa tapis: tidak ada teks tapis', () => {
        const wrapper = mountPage(props())

        expect(wrapper.text()).not.toContain('Tahap yang memakai')
        expect(wrapper.findAll('a').some((a) => a.text() === 'Tampilkan semua')).toBe(false)
    })

    it('tapis Form: teks tapis dan tautan Tampilkan semua ke pustaka', () => {
        const wrapper = mountPage(
            props({ filterForm: { id: 7, title: 'Laporan Monev', isActive: true } })
        )

        expect(wrapper.text()).toContain('Tahap yang memakai Laporan Monev')
        const all = wrapper.findAll('a').find((a) => a.text() === 'Tampilkan semua')
        expect(all?.attributes('href')).toBe('/admin.phases.index')
    })

    it('[B5] tapis Form: tautan Edit kartu membawa Form tapisnya', () => {
        const wrapper = mountPage(
            props({ filterForm: { id: 7, title: 'Laporan Monev', isActive: true } })
        )

        expect(wrapper.find('a[href="/admin.phases.edit/1/7"]').text()).toContain('Edit')
    })

    it('tapis Form: Buat Tahap Baru membawa Form itu sebagai akses bawaan', () => {
        const wrapper = mountPage(
            props({ filterForm: { id: 7, title: 'Laporan Monev', isActive: true } })
        )

        expect(wrapper.find('a[href="/admin.phases.create/7"]').text()).toContain('Buat Tahap Baru')
    })

    it('kosong: EmptyState', () => {
        const wrapper = mountPage(props({ phases: [] }))

        expect(wrapper.text()).toContain('Belum ada tahap.')
        expect(wrapper.findAll('article')).toHaveLength(0)
    })

    it('kosong dengan tapis: EmptyState menyebut Form-nya', () => {
        const wrapper = mountPage(
            props({ phases: [], filterForm: { id: 7, title: 'Laporan Monev', isActive: true } })
        )

        expect(wrapper.text()).toContain('Belum ada tahap yang memakai Laporan Monev.')
    })

    it('[FE-DELETE-06] hapus tahap berpengajuan membuka penjelasan berangka, bukan konfirmasi', async () => {
        const wrapper = mountPage(props())
        await wrapper.findAll('article')[1].findAll('button').at(-1)?.trigger('click')
        await flushPromises()

        expect(document.body.textContent).toContain('Tahap tidak bisa dihapus')
        expect(document.body.textContent).toContain('Tahap ini dirujuk 4 pengajuan')
        expect(document.body.textContent).not.toContain('Hapus tahap?')
        expect(router.delete).not.toHaveBeenCalled()
    })

    it('penjelasan menawarkan Nonaktifkan Tahap hanya bila tahap masih aktif', async () => {
        const wrapper = mountPage(props({ phases: [phaseCard({ submissionCount: 2 })] }))
        await clickButton(wrapper, 'Hapus')

        expect(bodyButton('Nonaktifkan Tahap')).toBeDefined()
    })

    it('[FE-DELETE-09] tahap kosong dikonfirmasi lalu dihapus', async () => {
        const wrapper = mountPage(props())
        await clickButton(wrapper, 'Hapus')

        expect(document.body.textContent).toContain('Hapus tahap?')
        expect(document.body.textContent).toContain(
            'Pengajuan Proposal beserta 2 detail dan form evaluasinya dihapus permanen.'
        )
        bodyButton('Hapus Tahap')?.click()
        await flushPromises()
        expect(router.delete).toHaveBeenCalledWith('/admin.phases.destroy/1', expect.anything())
    })

    it('nonaktifkan dikonfirmasi lalu memanggil route active', async () => {
        const wrapper = mountPage(props())
        await clickButton(wrapper, 'Nonaktifkan')

        expect(document.body.textContent).toContain('Nonaktifkan tahap?')
        bodyButton('Nonaktifkan Tahap')?.click()
        await flushPromises()
        expect(router.patch).toHaveBeenCalledWith(
            '/admin.phases.active/1',
            { active: false },
            expect.anything()
        )
    })

    it('mengaktifkan tahap nonaktif memanggil route active dengan true', async () => {
        const wrapper = mountPage(props({ phases: [phaseCard({ isActive: false })] }))
        await clickButton(wrapper, 'Aktifkan')

        expect(document.body.textContent).toContain('Aktifkan tahap?')
        bodyButton('Aktifkan Tahap')?.click()
        await flushPromises()
        expect(router.patch).toHaveBeenCalledWith(
            '/admin.phases.active/1',
            { active: true },
            expect.anything()
        )
    })
})
