import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router, put } = vi.hoisted(() => ({
    router: { post: vi.fn(), delete: vi.fn() },
    put: vi.fn(),
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        router,
        useForm: <T extends object>(data: T) =>
            reactive({ ...data, processing: false, errors: {}, put, clearErrors: vi.fn() }),
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
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import UserShowPage from '@/Pages/Identity/UserShowPage.vue'
import type { DeactivationBlockerData, UserShowPageProps } from '@/types/identity'

const underReview = { key: 'under_review', label: 'Dinilai', variant: 'default' } as const

const blocker = (overrides: Partial<DeactivationBlockerData> = {}): DeactivationBlockerData => ({
    submissionId: 42,
    title: 'Analisis Potensi Biochar',
    status: underReview,
    hasActiveCoInvestigator: true,
    manageUrl: '/admin/submissions/42',
    ...overrides,
})

const props = (overrides: Partial<UserShowPageProps> = {}): UserShowPageProps => ({
    user: {
        id: 5,
        name: 'Dr. Rina Wijaya',
        email: 'rina.wijaya@itk.ac.id',
        nidn: '0987654321',
        phone: '0813-9876-5432',
        organization: 'FSTI: Sistem Informasi',
        expertise: 'Data Science',
        registeredAt: '2025-09-03T08:00:00+08:00',
        status: {
            active: true,
            verification: null,
            online: false,
            lastActiveAt: '2026-11-02T07:00:00+08:00',
            checkedAt: '2026-11-02T08:00:00+08:00',
        },
    },
    access: {
        roles: [{ value: 'researcher', label: 'Peneliti' }],
        directPermissions: [
            {
                value: 'reporting.export',
                label: 'Mengekspor laporan',
                description: 'Mengunduh laporan.',
            },
        ],
        effectivePermissions: [
            {
                value: 'submissions.create',
                label: 'Membuat pengajuan',
                description: 'Membuat pengajuan baru.',
            },
            {
                value: 'reporting.export',
                label: 'Mengekspor laporan',
                description: 'Mengunduh laporan.',
            },
        ],
        assignableRoles: [{ value: 'operator', label: 'Operator' }],
        grantablePermissions: [
            {
                value: 'users.verify',
                label: 'Memverifikasi NIDN',
                description: 'Memverifikasi NIDN.',
            },
        ],
    },
    summary: {
        activeLeadSubmissions: 0,
        runningReviews: 2,
        registeredAt: '2025-09-03T08:00:00+08:00',
    },
    deactivationBlockers: [],
    submissions: [
        { id: 42, title: 'Analisis Biochar', role: 'Ketua', status: underReview, url: '/s/42' },
        { id: 43, title: 'Draf Tanpa Kirim', role: 'Anggota', status: underReview, url: null },
    ],
    reviews: [
        {
            id: 9,
            submissionId: 50,
            title: 'Kajian Mangrove',
            assignmentStatus: { value: 'replaced', label: 'Diganti' },
            evaluationStatus: { value: 'draft', label: 'Draf' },
            assignedAt: '2026-01-10T08:00:00+08:00',
        },
    ],
    tab: 'profile',
    reviewerTypeOptions: [
        { value: 'internal', label: 'Internal' },
        { value: 'external', label: 'Eksternal' },
    ],
    can: {
        deactivate: true,
        changeRoles: true,
        managePermissions: true,
        appointReviewer: true,
        endSessions: true,
        reactivate: true,
    },
    ...overrides,
})

const route = (name: string, param?: number) =>
    param === undefined ? `/${name}` : `/${name}/${param}`

const mountPage = (overrides: Partial<UserShowPageProps> = {}) =>
    mount(UserShowPage, { props: props(overrides), global: { mocks: { route } } })

const button = (wrapper: ReturnType<typeof mountPage>, label: string) =>
    wrapper.findAll('button').find((b) => b.text() === label)

const confirmDialog = (wrapper: ReturnType<typeof mountPage>, label: string) => {
    const found = wrapper
        .findAllComponents(ConfirmDialog)
        .find((d) => d.props('confirmLabel') === label)
    if (!found) throw new Error(`ConfirmDialog "${label}" tidak ada`)
    return found
}

beforeEach(() => {
    vi.stubGlobal('route', route)
    router.post.mockClear()
    router.delete.mockClear()
    put.mockClear()
})

describe('UserShowPage', () => {
    it('header: nama, "email · NIDN", badge status', () => {
        const text = mountPage().text()

        expect(text).toContain('Dr. Rina Wijaya')
        expect(text).toContain('rina.wijaya@itk.ac.id · NIDN 0987654321')
        expect(text).toContain('Aktif')
    })

    it('tab Profil, Riwayat Submission (n), Riwayat Penilai (n); tab awal dari prop tab', () => {
        const tabs = mountPage()
            .findAll('button[role="tab"]')
            .map((t) => t.text())

        expect(tabs).toEqual(['Profil', 'Riwayat Pengajuan (2)', 'Riwayat Penilai (1)'])
        expect(mountPage({ tab: 'reviews' }).text()).toContain('Kajian Mangrove')
        expect(mountPage().text()).not.toContain('Kajian Mangrove')
    })

    it('Tunjuk sebagai Reviewer hanya bila can.appointReviewer', async () => {
        const wrapper = mountPage()
        await button(wrapper, 'Tunjuk sebagai Penilai')?.trigger('click')
        await nextTick()

        const appoint = wrapper
            .findAllComponents(ActionDialog)
            .find((d) => d.props('title') === 'Tunjuk sebagai Penilai')
        expect(appoint?.props('open')).toBe(true)

        const base = props()
        const denied = mountPage({ can: { ...base.can, appointReviewer: false } })
        expect(button(denied, 'Tunjuk sebagai Penilai')).toBeUndefined()
    })

    it('[IAM-DEACT-07] tidak ada aksi hapus; aksi yang tersedia Nonaktifkan bila can.deactivate', () => {
        const wrapper = mountPage()

        expect(wrapper.text()).not.toContain('Hapus')
        expect(button(wrapper, 'Nonaktifkan')).toBeDefined()
        const denied = mountPage({
            can: {
                deactivate: false,
                changeRoles: false,
                managePermissions: false,
                appointReviewer: false,
                endSessions: false,
                reactivate: false,
            },
        })
        expect(button(denied, 'Nonaktifkan')).toBeUndefined()
    })

    it('[SM-OWNER-03] penghalang deaktivasi menonaktifkan tombol dan menaut Alihkan Kepemilikan ke manageUrl', () => {
        const wrapper = mountPage({ deactivationBlockers: [blocker()] })

        expect(wrapper.text()).toContain(
            'Dr. Rina Wijaya masih menjadi ketua 1 pengajuan aktif. Alihkan kepemilikannya lebih dulu.'
        )
        expect(button(wrapper, 'Nonaktifkan')?.attributes('disabled')).toBeDefined()
        expect(wrapper.find('a[href="/admin/submissions/42"]').text()).toContain(
            'Alihkan Kepemilikan'
        )
    })

    it('[SM-OWNER-04] penghalang tanpa co-investigator menyebut kekurangannya', () => {
        const wrapper = mountPage({
            deactivationBlockers: [blocker({ hasActiveCoInvestigator: false })],
        })

        expect(wrapper.text()).toContain(
            'Pengajuan "Analisis Potensi Biochar" belum punya co-investigator aktif, jadi belum ada calon penerima kepemilikan. Tambahkan co-investigator lebih dulu.'
        )
        expect(wrapper.text()).not.toContain('Alihkan Kepemilikan')
    })

    it('[IAM-DEACT-03] akun nonaktif: banner jejak tetap tersimpan, riwayat tetap tampil', () => {
        const base = props()
        const wrapper = mountPage({
            user: { ...base.user, status: { active: false, verification: null } },
            tab: 'submissions',
        })

        expect(wrapper.text()).toContain(
            'Akun nonaktif. Data dan seluruh jejaknya tetap tersimpan; yang berhenti hanya kemampuan masuk dan menerima penugasan baru.'
        )
        expect(wrapper.text()).toContain('Analisis Biochar')
        expect(button(wrapper, 'Nonaktifkan')).toBeUndefined()
    })

    it('Nonaktifkan membuka ConfirmDialog lalu router.post ke admin.users.deactivate', async () => {
        const wrapper = mountPage()

        await button(wrapper, 'Nonaktifkan')?.trigger('click')
        const dialog = confirmDialog(wrapper, 'Nonaktifkan')
        expect(dialog.props('open')).toBe(true)
        dialog.vm.$emit('confirm')

        expect(router.post).toHaveBeenCalledWith(
            '/admin.users.deactivate/5',
            {},
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('[IAM-DEACT-11] dialog Nonaktifkan menyebut dampaknya', async () => {
        const wrapper = mountPage()

        await button(wrapper, 'Nonaktifkan')?.trigger('click')
        const description = confirmDialog(wrapper, 'Nonaktifkan').props('description')

        expect(description).toContain(
            '2 penugasan penilaian berjalan akan tetap ada dan perlu diganti Operator.'
        )
        expect(description).toContain('Sesi yang sedang masuk akan diakhiri.')
        expect(description).toContain('Pengajuan, evaluasi, dan riwayatnya tetap tersimpan.')
    })

    it('[IAM-PERM-03] hak akses efektif tampil sebagai gabungan', () => {
        const text = mountPage().text()

        expect(text).toContain('Membuat pengajuan')
        expect(text).toContain('Mengekspor laporan')
        expect(text).toContain(
            'Hak akses dari peran dan hak akses langsung berdiri sendiri, mencabut salah satunya tidak mencabut yang lain. Perubahan peran dan hak akses hanya dapat dilakukan oleh Admin.'
        )
    })

    it('[IAM-PERM-02][IAM-PERM-04] mencabut hak akses langsung memanggil router.delete dengan permission-nya', async () => {
        const wrapper = mountPage()
        const revokes = wrapper.findAll('button').filter((b) => b.text().startsWith('Cabut'))

        expect(revokes).toHaveLength(1)
        await revokes[0].trigger('click')

        expect(router.delete).toHaveBeenCalledWith(
            '/admin.users.permissions.revoke/5',
            expect.objectContaining({ data: { permission: 'reporting.export' } })
        )
    })

    it('menambah hak akses langsung memilih dari grantablePermissions lalu router.post', async () => {
        const wrapper = mountPage()
        const select = wrapper.findComponent(OptionSelect)

        expect(select.props('options')).toEqual([
            {
                value: 'users.verify',
                label: 'Memverifikasi NIDN',
                description: 'Memverifikasi NIDN.',
            },
        ])
        select.vm.$emit('update:modelValue', 'users.verify')
        await nextTick()
        await button(wrapper, 'Tambah')?.trigger('click')

        expect(router.post).toHaveBeenCalledWith(
            '/admin.users.permissions.grant/5',
            { permission: 'users.verify' },
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('tanpa can.managePermissions tidak ada tombol tambah/cabut', () => {
        const wrapper = mountPage({
            can: {
                deactivate: true,
                changeRoles: true,
                managePermissions: false,
                appointReviewer: false,
                endSessions: false,
                reactivate: false,
            },
        })

        expect(button(wrapper, 'Tambah')).toBeUndefined()
        expect(wrapper.findAll('button').some((b) => b.text().startsWith('Cabut'))).toBe(false)
    })

    it('riwayat submission menaut url bila ada, teks biasa bila null', () => {
        const wrapper = mountPage({ tab: 'submissions' })

        expect(wrapper.find('a[href="/s/42"]').text()).toBe('Analisis Biochar')
        expect(wrapper.text()).toContain('Draf Tanpa Kirim')
        expect(wrapper.findAll('a').some((a) => a.text() === 'Draf Tanpa Kirim')).toBe(false)
    })

    it('Ubah Peran hanya bila can.changeRoles dan mengirim roles tercentang', async () => {
        const denied = mountPage({
            can: {
                deactivate: true,
                changeRoles: false,
                managePermissions: true,
                appointReviewer: false,
                endSessions: false,
                reactivate: false,
            },
        })
        expect(button(denied, 'Ubah Peran')).toBeUndefined()

        const base = props()
        const wrapper = mount(UserShowPage, {
            props: props({
                access: {
                    ...base.access,
                    roles: [
                        { value: 'researcher', label: 'Peneliti' },
                        { value: 'reviewer_internal', label: 'Penilai (internal)' },
                    ],
                    assignableRoles: [
                        { value: 'researcher', label: 'Peneliti' },
                        { value: 'operator', label: 'Operator' },
                    ],
                },
            }),
            global: { mocks: { route } },
            attachTo: document.body,
        })
        await button(wrapper, 'Ubah Peran')?.trigger('click')
        await nextTick()
        expect(document.body.textContent).toContain('Tidak diubah di sini: Penilai (internal)')

        document.querySelector<HTMLButtonElement>('#assign-role-operator')?.click()
        await nextTick()
        wrapper
            .findAllComponents(ActionDialog)
            .find((d) => d.props('title') === 'Ubah Peran')
            ?.vm.$emit('submit')

        expect(put).toHaveBeenCalledWith(
            '/admin.users.roles.update/5',
            expect.objectContaining({ preserveScroll: true })
        )
        const form = put.mock.contexts[0] as { roles: string[] }
        expect([...form.roles].sort()).toEqual(['operator', 'researcher'])
        wrapper.unmount()
    })

    it('[IAM-SESSION-01] Akhiri Semua Sesi lewat ConfirmDialog lalu router.post', async () => {
        const wrapper = mountPage()

        expect(wrapper.text()).toContain('Terakhir aktif')
        await button(wrapper, 'Akhiri Semua Sesi')?.trigger('click')
        const dialog = confirmDialog(wrapper, 'Akhiri Semua Sesi')
        expect(dialog.props('open')).toBe(true)
        dialog.vm.$emit('confirm')

        expect(router.post).toHaveBeenCalledWith(
            '/admin.users.sessions.end/5',
            {},
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('[IAM-SESSION-03] tanpa can.endSessions tidak ada tombol Akhiri Semua Sesi', () => {
        const base = props()
        const wrapper = mountPage({ can: { ...base.can, endSessions: false } })

        expect(button(wrapper, 'Akhiri Semua Sesi')).toBeUndefined()
    })

    it('[IAM-DEACT-03][IAM-REACT-04] tanpa can.reactivate tidak ada tombol Aktifkan', () => {
        const base = props()
        const wrapper = mountPage({
            user: { ...base.user, status: { ...base.user.status, active: false } },
            can: { ...base.can, reactivate: false },
        })

        expect(button(wrapper, 'Aktifkan')).toBeUndefined()
    })

    it('[IAM-REACT-01] Aktifkan pada akun nonaktif lewat ConfirmDialog lalu router.post', async () => {
        const base = props()
        const wrapper = mountPage({
            user: { ...base.user, status: { ...base.user.status, active: false } },
        })

        expect(button(wrapper, 'Nonaktifkan')).toBeUndefined()
        await button(wrapper, 'Aktifkan')?.trigger('click')
        const dialog = confirmDialog(wrapper, 'Aktifkan')
        expect(dialog.props('open')).toBe(true)
        dialog.vm.$emit('confirm')

        expect(router.post).toHaveBeenCalledWith(
            '/admin.users.reactivate/5',
            {},
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('akun aktif tidak menawarkan Aktifkan', () => {
        expect(button(mountPage(), 'Aktifkan')).toBeUndefined()
    })
})
