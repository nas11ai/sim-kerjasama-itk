import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router, post, page } = vi.hoisted(() => ({
    router: { delete: vi.fn() },
    post: vi.fn(),
    page: { flash: {} as { issuedInvitationUrl?: string } },
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        router,
        usePage: () => page,
        useForm: <T extends object>(data: T) =>
            reactive({ ...data, processing: false, errors: {}, post, reset: vi.fn() }),
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

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import InvitationIndexPage from '@/Pages/Identity/InvitationIndexPage.vue'
import type { InvitationIndexPageProps, InvitationRowData } from '@/types/identity'

const preset = { value: 'researcher', label: 'Membuat & mengelola pengajuan' }

const row = (overrides: Partial<InvitationRowData> = {}): InvitationRowData => ({
    id: 3,
    organization: 'Pusat Studi Energi',
    organizationId: 21,
    preset,
    usedCount: 3,
    maxUses: 5,
    remainingUses: 2,
    expiresAt: '2026-12-31T23:59:59+08:00',
    status: { value: 'valid', label: 'Valid' },
    revocable: true,
    ...overrides,
})

const props = (overrides: Partial<InvitationIndexPageProps> = {}): InvitationIndexPageProps => ({
    invitations: [row()],
    organizationOptions: [
        { value: '21', label: 'Pusat Studi Energi' },
        { value: '22', label: 'FTI: Teknik Mesin' },
    ],
    presetOptions: [preset],
    ...overrides,
})

const route = (name: string, param?: number) =>
    param === undefined ? `/${name}` : `/${name}/${param}`

const mountPage = (overrides: Partial<InvitationIndexPageProps> = {}) =>
    mount(InvitationIndexPage, {
        props: props(overrides),
        global: { mocks: { route } },
        attachTo: document.body,
    })

type Wrapper = ReturnType<typeof mountPage>

const buttons = (wrapper: Wrapper, label: string) =>
    wrapper.findAll('button').filter((b) => b.text() === label)

beforeEach(() => {
    vi.stubGlobal('route', route)
    router.delete.mockClear()
    post.mockClear()
    page.flash = {}
    document.body.innerHTML = ''
})

describe('InvitationIndexPage', () => {
    it('form "Buat Undangan Baru": Organisasi Tujuan, Hak Akses, Maks. Pemakaian, Berlaku Sampai, tombol "Buat Undangan"', async () => {
        const wrapper = mountPage()
        const text = wrapper.text()

        expect(wrapper.find('h1').text()).toBe('Undangan Registrasi')
        expect(text).toContain(
            'Undang peneliti eksternal lewat tautan registrasi, akun langsung aktif tanpa verifikasi NIDN.'
        )
        for (const label of [
            'Buat Undangan Baru',
            'Organisasi Tujuan',
            'Hak Akses',
            'Maks. Pemakaian',
            'Berlaku Sampai',
        ]) {
            expect(text).toContain(label)
        }

        await wrapper.find('form').trigger('submit')
        expect(post).toHaveBeenCalledWith(
            '/admin.invitations.store',
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('tautan undangan tampil sekali dari flash dengan tombol Salin Link', async () => {
        const writeText = vi.fn(() => Promise.resolve())
        vi.stubGlobal('navigator', { clipboard: { writeText } })
        page.flash = { issuedInvitationUrl: 'https://simpas.test/register/invitation/abc' }
        const wrapper = mountPage()

        expect(wrapper.text()).toContain('https://simpas.test/register/invitation/abc')
        await buttons(wrapper, 'Salin Tautan')[0]?.trigger('click')
        expect(writeText).toHaveBeenCalledWith('https://simpas.test/register/invitation/abc')

        page.flash = {}
        expect(mountPage().text()).not.toContain('register/invitation')
    })

    it('[IAM-REG-09] kolom "Terpakai / Kuota" berbunyi "3 / 5"; tanpa kuota "3 / ∞"', () => {
        const text = mountPage({
            invitations: [row(), row({ id: 4, maxUses: null, remainingUses: null })],
        }).text()

        expect(text).toContain('Terpakai / Kuota')
        expect(text).toContain('3 / 5')
        expect(text).toContain('3 / ∞')
        expect(text).toContain('31 Des 2026')
    })

    it('[IAM-INVITE-06] baris dicabut tetap tampil dengan badge Dicabut dan jumlah pemakaian', () => {
        const text = mountPage({
            invitations: [
                row({
                    usedCount: 1,
                    maxUses: 10,
                    status: { value: 'revoked', label: 'Dicabut' },
                    revocable: false,
                }),
            ],
        }).text()

        expect(text).toContain('Dicabut')
        expect(text).toContain('1 / 10')
    })

    it('[IAM-REG-16][IAM-INVITE-04] Cabut membuka konfirmasi', async () => {
        const wrapper = mountPage({ invitations: [row({ remainingUses: 9 })] })

        await buttons(wrapper, 'Cabut')[0]?.trigger('click')
        await nextTick()
        const confirm = wrapper
            .findAllComponents(ConfirmDialog)
            .find((d) => d.props('confirmLabel') === 'Cabut Undangan')
        if (!confirm) throw new Error('konfirmasi cabut tidak ada')

        expect(confirm.props('open')).toBe(true)
        expect(confirm.props('description')).toBe(
            'Mencabut undangan ini akan menghentikan 9 pemakaian yang tersisa. Akun yang sudah terlanjur dibuat tidak terpengaruh.'
        )
        expect(router.delete).not.toHaveBeenCalled()

        confirm.vm.$emit('confirm')
        expect(router.delete).toHaveBeenCalledWith(
            '/admin.invitations.revoke/3',
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('baris tidak valid menawarkan Buat Ulang yang mengisi form, tanpa Cabut', async () => {
        const wrapper = mountPage({
            invitations: [
                row({
                    id: 5,
                    organization: 'FTI: Teknik Mesin',
                    organizationId: 22,
                    usedCount: 5,
                    remainingUses: 0,
                    status: { value: 'exhausted', label: 'Kuota Habis' },
                }),
            ],
        })

        expect(buttons(wrapper, 'Cabut')).toHaveLength(0)
        await buttons(wrapper, 'Buat Ulang')[0]?.trigger('click')
        await nextTick()

        const selects = wrapper.findAllComponents(OptionSelect)
        expect(selects[0]?.props('modelValue')).toBe('22')
        expect(selects[1]?.props('modelValue')).toBe('researcher')
    })

    it('catatan kaki frame tampil', () => {
        expect(mountPage().text()).toContain(
            'Undangan kedaluwarsa atau kuotanya habis otomatis tidak berlaku lagi dan tidak bisa dipakai mendaftar. Mencabut undangan memerlukan konfirmasi.'
        )
    })
})
