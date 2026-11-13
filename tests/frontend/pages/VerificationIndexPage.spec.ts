import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router, post, formErrors } = vi.hoisted(() => ({
    router: { post: vi.fn() },
    post: vi.fn(),
    formErrors: { value: {} as Record<string, string> },
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        router,
        useForm: <T extends object>(data: T) =>
            reactive({
                ...data,
                processing: false,
                errors: { ...formErrors.value },
                post,
                reset: vi.fn(),
            }),
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
import VerificationIndexPage from '@/Pages/Identity/VerificationIndexPage.vue'
import type { VerificationCardData, VerificationIndexPageProps } from '@/types/identity'

const card = (overrides: Partial<VerificationCardData> = {}): VerificationCardData => ({
    profileId: 11,
    name: 'Dr. Maya Sari',
    email: 'maya.sari@itk.ac.id',
    nidn: '0654321098',
    organization: 'FSTI: Matematika',
    registeredAt: '2026-07-01T08:00:00+08:00',
    rejectedAt: null,
    rejectionReason: null,
    ...overrides,
})

const rejectedCard = card({
    profileId: 12,
    name: 'Dr. Hasan Basri',
    email: 'hasan.basri@itk.ac.id',
    nidn: '0543210978',
    rejectedAt: '2026-07-03T08:00:00+08:00',
    rejectionReason: 'NIDN tidak ditemukan di pangkalan data PDDikti atas nama yang sama.',
})

const props = (
    overrides: Partial<VerificationIndexPageProps> = {}
): VerificationIndexPageProps => ({
    pending: [card()],
    rejected: [],
    ...overrides,
})

const route = (name: string, param?: number) =>
    param === undefined ? `/${name}` : `/${name}/${param}`

const mountPage = (overrides: Partial<VerificationIndexPageProps> = {}) =>
    mount(VerificationIndexPage, {
        props: props(overrides),
        global: { mocks: { route } },
        attachTo: document.body,
    })

type Wrapper = ReturnType<typeof mountPage>

const buttons = (wrapper: Wrapper, label: string) =>
    wrapper.findAll('button').filter((b) => b.text() === label)

const openReject = async (wrapper: Wrapper) => {
    await buttons(wrapper, 'Tolak')[0]?.trigger('click')
    await nextTick()
}

const rejectDialog = (wrapper: Wrapper) =>
    wrapper
        .findAllComponents(ActionDialog)
        .find((d) => d.props('submitLabel') === 'Tolak Pendaftaran')

beforeEach(() => {
    vi.stubGlobal('route', route)
    router.post.mockClear()
    post.mockClear()
    formErrors.value = {}
    document.body.innerHTML = ''
})

describe('VerificationIndexPage', () => {
    it('header "Antrian Verifikasi NIDN" dengan badge "{n} menunggu" dan tombol kembali "Daftar User"', () => {
        const wrapper = mountPage({ pending: [card(), card({ profileId: 13 })] })

        expect(wrapper.find('h1').text()).toBe('Antrian Verifikasi NIDN')
        expect(wrapper.text()).toContain('2 menunggu')
        const back = wrapper.find('a[href="/admin.users.index"]')
        expect(back.text()).toContain('Daftar Pengguna')
    })

    it('kartu pending: nama, email, "NIDN: … · organisasi · daftar tanggal", tombol Verifikasi dan Tolak', async () => {
        const wrapper = mountPage()
        const text = wrapper.text()

        expect(text).toContain('Dr. Maya Sari')
        expect(text).toContain('maya.sari@itk.ac.id')
        expect(text).toContain('NIDN: 0654321098 · FSTI: Matematika · daftar 01 Jul 2026')
        expect(buttons(wrapper, 'Tolak')).toHaveLength(1)

        await buttons(wrapper, 'Verifikasi')[0]?.trigger('click')
        expect(router.post).toHaveBeenCalledWith(
            '/admin.verifications.verify/11',
            {},
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('[IAM-VERIFY-07] Tolak membuka dialog berlabel "Alasan penolakan" dengan hint "Dikirimkan ke pendaftar dan tersimpan sebagai jejak keputusan." lalu post reason', async () => {
        const wrapper = mountPage()
        await openReject(wrapper)

        const body = document.body.textContent ?? ''
        expect(body).toContain('Alasan penolakan')
        expect(body).toContain('Dikirimkan ke pendaftar dan tersimpan sebagai jejak keputusan.')
        expect(body).toContain('Dr. Maya Sari')

        const textarea = document.body.querySelector<HTMLTextAreaElement>('textarea')
        if (!textarea) throw new Error('textarea alasan tidak ada')
        textarea.value = 'NIDN tidak cocok'
        textarea.dispatchEvent(new Event('input'))
        await nextTick()

        rejectDialog(wrapper)?.vm.$emit('submit')
        expect(post).toHaveBeenCalledWith(
            '/admin.verifications.reject/11',
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('[IAM-VERIFY-08] tombol "Tolak Pendaftaran" nonaktif selama alasan kosong atau spasi', async () => {
        const wrapper = mountPage()
        await openReject(wrapper)

        expect(rejectDialog(wrapper)?.props('submitDisabled')).toBe(true)

        const textarea = document.body.querySelector<HTMLTextAreaElement>('textarea')
        if (!textarea) throw new Error('textarea alasan tidak ada')
        textarea.value = '   '
        textarea.dispatchEvent(new Event('input'))
        await nextTick()
        expect(rejectDialog(wrapper)?.props('submitDisabled')).toBe(true)

        textarea.value = 'NIDN tidak cocok'
        textarea.dispatchEvent(new Event('input'))
        await nextTick()
        expect(rejectDialog(wrapper)?.props('submitDisabled')).toBe(false)
    })

    it('[IAM-VERIFY-10] kartu ditolak: badge Ditolak, "ditolak {tanggal}", banner "Alasan penolakan sebelumnya: …", tombol Verifikasi, tanpa Tolak', () => {
        const wrapper = mountPage({ pending: [], rejected: [rejectedCard] })
        const text = wrapper.text()

        expect(text).toContain('Ditolak')
        expect(text).toContain('ditolak 03 Jul 2026')
        expect(text).toContain(
            'Alasan penolakan sebelumnya: NIDN tidak ditemukan di pangkalan data PDDikti atas nama yang sama.'
        )
        expect(buttons(wrapper, 'Verifikasi')).toHaveLength(1)
        expect(buttons(wrapper, 'Tolak')).toHaveLength(0)
    })

    it('[IAM-REG-14] banner jalur undangan tampil', () => {
        const text = mountPage().text()

        expect(text).toContain(
            'Menolak pendaftaran wajib menyertakan alasan, alasan dikirimkan ke pendaftar.'
        )
        expect(text).toContain(
            'Antrian ini hanya memuat pendaftar jalur mandiri. Peneliti yang masuk lewat undangan tidak punya NIDN untuk diperiksa dan akunnya sudah aktif sejak mendaftar.'
        )
    })

    it('Koreksi NIDN hanya di kartu ditolak, memposting nidn, dan menampilkan form.errors.nidn', async () => {
        const wrapper = mountPage({ pending: [card()], rejected: [rejectedCard] })

        expect(buttons(wrapper, 'Koreksi NIDN')).toHaveLength(1)
        await buttons(wrapper, 'Koreksi NIDN')[0]?.trigger('click')
        await nextTick()

        const dialog = wrapper
            .findAllComponents(ActionDialog)
            .find((d) => d.props('submitLabel') === 'Simpan Koreksi')
        if (!dialog) throw new Error('dialog koreksi tidak ada')
        const input = document.body.querySelector<HTMLInputElement>('input#correct-nidn')
        expect(input?.value).toBe('0543210978')

        dialog.vm.$emit('submit')
        expect(post).toHaveBeenCalledWith(
            '/admin.verifications.nidn/12',
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('Koreksi NIDN menampilkan galat nidn dari server', async () => {
        formErrors.value = { nidn: 'NIDN ini sudah terdaftar pada akun lain.' }
        const wrapper = mountPage({ pending: [], rejected: [rejectedCard] })
        await buttons(wrapper, 'Koreksi NIDN')[0]?.trigger('click')
        await nextTick()

        expect(document.body.textContent).toContain('NIDN ini sudah terdaftar pada akun lain.')
    })

    it('EmptyState saat antrian kosong', () => {
        const text = mountPage({ pending: [], rejected: [] }).text()

        expect(text).toContain('Tidak ada pendaftar yang menunggu verifikasi')
        expect(text).toContain('0 menunggu')
    })
})
