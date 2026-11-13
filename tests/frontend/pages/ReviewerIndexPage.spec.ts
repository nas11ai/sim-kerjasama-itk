import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router, post, put } = vi.hoisted(() => ({
    router: { get: vi.fn(), reload: vi.fn() },
    post: vi.fn(),
    put: vi.fn(),
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        router,
        useForm: <T extends object>(data: T) =>
            reactive({ ...data, processing: false, errors: {}, post, put, reset: vi.fn() }),
        Head: defineComponent({ setup: () => () => null }),
        Link: defineComponent({
            props: { href: { type: String, required: true } },
            setup:
                (props, { slots }) =>
                () =>
                    h('a', { href: props.href }, slots.default?.()),
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

import ActionDialog from '@/Components/app/ActionDialog.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import ReviewerIndexPage from '@/Pages/Identity/ReviewerIndexPage.vue'
import type {
    AppointmentCandidateData,
    ReviewerIndexPageProps,
    ReviewerRowData,
    ReviewerStanding,
} from '@/types/identity'

const internal = { value: 'internal', label: 'Internal' }
const external = { value: 'external', label: 'Eksternal' }

const standings: Record<ReviewerStanding, string> = {
    active: 'Aktif',
    not_started: 'Belum Mulai',
    ended: 'Berakhir',
    account_inactive: 'Akun Nonaktif',
}

const reviewer = (overrides: Partial<ReviewerRowData> = {}): ReviewerRowData => ({
    id: 1,
    userId: 10,
    name: 'Dr. Rina Wijaya',
    type: internal,
    tenureStart: '2025-09-01',
    tenureEnd: '2026-08-31',
    runningAssignments: 3,
    standing: { value: 'active', label: 'Aktif' },
    historyUrl: '/admin/users/10?tab=reviews',
    ...overrides,
})

const withStanding = (value: ReviewerStanding, overrides: Partial<ReviewerRowData> = {}) =>
    reviewer({ standing: { value, label: standings[value] }, ...overrides })

const candidate = (
    overrides: Partial<AppointmentCandidateData> = {}
): AppointmentCandidateData => ({
    userId: 20,
    name: 'Dr. Andi Mappa',
    email: 'andi@itk.ac.id',
    nidn: '0011223344',
    organization: 'FTI',
    currentReviewer: null,
    ...overrides,
})

const props = (overrides: Partial<ReviewerIndexPageProps> = {}): ReviewerIndexPageProps => ({
    reviewers: {
        data: [reviewer()],
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 1,
        from: 1,
        to: 1,
        prev_page_url: null,
        next_page_url: null,
    },
    filters: { search: '', type: '', standing: '' },
    typeOptions: [internal, external],
    standingOptions: Object.entries(standings).map(([value, label]) => ({ value, label })),
    can: { manageInvitations: true },
    ...overrides,
})

const rows = (...data: ReviewerRowData[]) => ({ ...props().reviewers, data, total: data.length })

const route = (name: string, param?: number) =>
    param === undefined ? `/${name}` : `/${name}/${param}`

const mountPage = (overrides: Partial<ReviewerIndexPageProps> = {}) =>
    mount(ReviewerIndexPage, {
        props: props(overrides),
        global: { mocks: { route } },
        attachTo: document.body,
    })

type Wrapper = ReturnType<typeof mountPage>

const buttons = (wrapper: Wrapper, label: string) =>
    wrapper.findAll('button').filter((b) => b.text() === label)

const dialog = (wrapper: Wrapper, title: string) =>
    wrapper.findAllComponents(ActionDialog).find((d) => d.props('title') === title)

const body = () => document.body.textContent ?? ''

beforeEach(() => {
    vi.stubGlobal('route', route)
    router.get.mockClear()
    router.reload.mockClear()
    post.mockClear()
    put.mockClear()
    document.body.innerHTML = ''
})

describe('ReviewerIndexPage', () => {
    it('header "Manajemen Reviewer", tombol "Tambah Reviewer", kolom Nama, Tipe, Periode Aktif, Beban Tugas, Status', () => {
        const wrapper = mountPage()
        const text = wrapper.text()

        expect(wrapper.find('h1').text()).toBe('Manajemen Penilai')
        expect(text).toContain('Semua penilai beserta periode aktif dan beban tugasnya.')
        expect(buttons(wrapper, 'Tambah Penilai')).toHaveLength(1)
        for (const label of ['Nama', 'Tipe', 'Periode Aktif', 'Beban Tugas', 'Status']) {
            expect(text).toContain(label)
        }
        expect(text).toContain('01 Sep 2025 – 31 Agu 2026')
        expect(text).toContain('3 aktif')
    })

    it('aksi baris menurut standing', () => {
        const wrapper = mountPage({
            reviewers: rows(
                withStanding('active', { id: 1 }),
                withStanding('not_started', { id: 2 }),
                withStanding('ended', { id: 3 }),
                withStanding('account_inactive', { id: 4, historyUrl: null })
            ),
        })

        expect(buttons(wrapper, 'Ubah Periode')).toHaveLength(2)
        expect(buttons(wrapper, 'Perpanjang')).toHaveLength(1)
        expect(wrapper.findAll('a').filter((a) => a.text().includes('Riwayat'))).toHaveLength(3)
    })

    it('[REV-ASSIGN-08] Perpanjang pada reviewer berakhir menampilkan banner masa tugas berakhir', async () => {
        const wrapper = mountPage({
            reviewers: rows(withStanding('ended', { name: 'Dr. Sri Handayani' })),
        })

        await buttons(wrapper, 'Perpanjang')[0]?.trigger('click')
        await nextTick()

        expect(body()).toContain(
            'Masa tugas Dr. Sri Handayani sudah berakhir. Perpanjang lebih dulu sebelum menugaskannya.'
        )
        dialog(wrapper, 'Perpanjang Masa Tugas')?.vm.$emit('submit')
        expect(put).toHaveBeenCalledWith(
            '/admin.reviewers.update/1',
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('[IAM-DEACT-04] reviewer akun nonaktif berbadge "Akun Nonaktif"', () => {
        const wrapper = mountPage({ reviewers: rows(withStanding('account_inactive')) })

        expect(wrapper.text()).toContain('Akun Nonaktif')
        expect(buttons(wrapper, 'Ubah Periode')).toHaveLength(0)
        expect(buttons(wrapper, 'Perpanjang')).toHaveLength(0)
    })

    it('[IAM-REVIEWER-03] pencarian tanpa hasil menampilkan EmptyState "Tidak ada user dengan nama itu" dan "Buat Undangan Registrasi" bila can.manageInvitations', async () => {
        const wrapper = mountPage({ appointmentCandidates: [] })
        await buttons(wrapper, 'Tambah Penilai')[0]?.trigger('click')
        await nextTick()

        const search = document.body.querySelector<HTMLInputElement>('input#appoint-search')
        if (!search) throw new Error('kolom cari kandidat tidak ada')
        search.value = 'Doni Prakoso'
        search.dispatchEvent(new Event('input'))
        await nextTick()

        expect(body()).toContain('Tidak ada pengguna dengan nama itu')
        const invite = Array.from(document.body.querySelectorAll('a')).find((a) =>
            a.textContent?.includes('Buat Undangan Registrasi')
        )
        expect(invite?.getAttribute('href')).toBe('/admin.invitations.index')

        wrapper.unmount()
        document.body.innerHTML = ''
        const denied = mountPage({ appointmentCandidates: [], can: { manageInvitations: false } })
        await buttons(denied, 'Tambah Penilai')[0]?.trigger('click')
        await nextTick()
        const input = document.body.querySelector<HTMLInputElement>('input#appoint-search')
        if (!input) throw new Error('kolom cari kandidat tidak ada')
        input.value = 'Doni'
        input.dispatchEvent(new Event('input'))
        await nextTick()
        expect(body()).not.toContain('Buat Undangan Registrasi')
    })

    it('[IAM-REVIEWER-04] kandidat yang sudah reviewer memunculkan banner pembaruan', async () => {
        const wrapper = mountPage({
            appointmentCandidates: [
                candidate({
                    name: 'Dr. Rina Wijaya',
                    currentReviewer: {
                        type: internal,
                        tenureStart: '2025-09-01',
                        tenureEnd: '2026-08-31',
                        standing: { value: 'active', label: 'Aktif' },
                    },
                }),
            ],
        })
        await buttons(wrapper, 'Tambah Penilai')[0]?.trigger('click')
        await nextTick()

        const pick = Array.from(document.body.querySelectorAll('button')).find((b) =>
            b.textContent?.includes('Dr. Rina Wijaya')
        )
        pick?.click()
        await nextTick()

        expect(body()).toContain(
            'Dr. Rina Wijaya sudah menjadi penilai. Menyimpan akan memperbarui tipe dan masa tugasnya, bukan menambah entri baru.'
        )
        dialog(wrapper, 'Tambah Penilai')?.vm.$emit('submit')
        expect(post).toHaveBeenCalledWith(
            '/admin.reviewers.store',
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('[IAM-REVIEWER-05] mengubah tipe internal → eksternal memunculkan banner pencabutan skor', async () => {
        const wrapper = mountPage()
        await buttons(wrapper, 'Ubah Periode')[0]?.trigger('click')
        await nextTick()

        const banner =
            'Mengubah tipe menjadi Eksternal akan mencabut kemampuan melihat skor penilai lain. Evaluasi yang sudah ditulis tidak berubah.'
        expect(body()).not.toContain(banner)

        const typeSelect = wrapper
            .findAllComponents(OptionSelect)
            .find((s) => s.props('id') === 'tenure-type')
        typeSelect?.vm.$emit('update:modelValue', 'external')
        await nextTick()

        expect(body()).toContain(banner)
    })

    it('banner bawah tabel frame tampil', () => {
        expect(mountPage().text()).toContain(
            'Menambah penilai memilih pengguna yang sudah terdaftar, tidak ada jalur registrasi mandiri untuk penilai. Penilai eksternal harus diundang lebih dulu lewat Undangan Registrasi.'
        )
    })
})
