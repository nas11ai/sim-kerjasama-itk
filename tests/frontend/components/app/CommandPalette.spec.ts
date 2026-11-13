import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, nextTick, ref } from 'vue'

import type { GlobalSearchResult, SearchHit } from '@/types/search'

const EMPTY: GlobalSearchResult = { submissions: [], users: [], reviewers: [] }

const term = ref('')
const results = ref<GlobalSearchResult>(EMPTY)
const loading = ref(false)
const failed = ref(false)
const tooShort = computed(() => term.value.trim().length < 2)

vi.mock('@/composables/useGlobalSearch', () => ({
    useGlobalSearch: () => ({ term, results, loading, failed, tooShort }),
}))

const visit = vi.fn()
const page = {
    props: {
        auth: { user: { permissions: ['submissions.view-own', 'submissions.create'] } },
        navigation: {
            hasOutputSubmission: false,
            hasMonevSubmission: false,
            unreadAnnouncements: 0,
            unreadNotifications: 0,
        },
    },
}
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: { visit: (url: string) => visit(url) },
}))

const { default: CommandPalette } = await import('@/Components/app/CommandPalette.vue')

const route = (name: string, params?: Record<string, string>) =>
    params === undefined ? `/${name}` : `/${name}/${Object.values(params).join('/')}`

let wrapper: ReturnType<typeof mount>

async function openPalette() {
    wrapper = mount(CommandPalette, {
        props: {
            open: true,
            'onUpdate:open': (value: boolean) => wrapper.setProps({ open: value }),
        },
        global: { mocks: { route } },
        attachTo: document.body,
    })
    await flushPromises()
}

const hit = (id: number, label: string, sublabel = ''): SearchHit => ({
    id,
    label,
    sublabel,
    url: `/hasil/${id}`,
})

const groupLabels = () =>
    Array.from(document.querySelectorAll('[data-palette-group-label]')).map((el) =>
        el.textContent?.trim()
    )
const status = () => document.querySelector('[role="status"]')
const option = (text: string) =>
    Array.from(document.querySelectorAll('[role="option"]')).find((el) =>
        el.textContent?.includes(text)
    ) as HTMLElement | undefined
const input = () => document.querySelector('input') as HTMLInputElement

beforeEach(() => {
    // jsdom tidak mengimplementasikan scrollIntoView, yang dipanggil Listbox saat menyorot item.
    Element.prototype.scrollIntoView = vi.fn()
    vi.stubGlobal('route', route)
    term.value = ''
    results.value = EMPTY
    loading.value = false
    failed.value = false
    visit.mockClear()
})

afterEach(() => {
    wrapper.unmount()
    document.body.innerHTML = ''
    vi.unstubAllGlobals()
})

describe('CommandPalette', () => {
    it('tanpa istilah: grup Halaman berisi item yang lolos izin dan petunjuk 2 huruf', async () => {
        await openPalette()

        expect(groupLabels()).toEqual(['Halaman'])
        expect(option('Beranda')).toBeDefined()
        expect(option('Daftar Pengajuan')).toBeDefined()
        expect(option('Semua Pengajuan')).toBeUndefined()
        expect(status()?.textContent).toContain(
            'Ketik minimal 2 huruf untuk mencari pengajuan dan pengguna.'
        )
    })

    it('hasil server tampil per grup dengan label dan sublabel; grup kosong tidak dirender', async () => {
        await openPalette()
        term.value = 'zz'
        results.value = {
            submissions: [hit(1, 'Penelitian Dasar', 'Menunggu Review · Dr. Rina')],
            users: [hit(2, 'Dr. Budi Santoso', 'NIDN 0012345678')],
            reviewers: [],
        }
        await nextTick()

        expect(groupLabels()).toEqual(['Pengajuan', 'Pengguna'])
        expect(option('Penelitian Dasar')?.textContent).toContain('Menunggu Review · Dr. Rina')
        expect(option('Dr. Budi Santoso')?.textContent).toContain('NIDN 0012345678')
    })

    it('pesan kosong, gagal, dan memuat berada di satu live region yang selalu dirender', async () => {
        await openPalette()
        const region = status()
        expect(region?.getAttribute('aria-live')).toBe('polite')

        term.value = 'zz'
        await nextTick()
        expect(status()).toBe(region)
        expect(region?.textContent).toContain('Tidak ada hasil.')

        loading.value = true
        await nextTick()
        expect(region?.textContent).toContain('Mencari…')

        loading.value = false
        failed.value = true
        term.value = 'dash'
        await nextTick()
        expect(status()).toBe(region)
        expect(region?.textContent).toContain('Pencarian gagal. Coba lagi.')
        expect(groupLabels()).toEqual(['Halaman'])
        expect(option('Beranda')).toBeDefined()
    })

    it('memilih hit server dengan klik membuka url-nya lalu menutup palette', async () => {
        await openPalette()
        term.value = 'zz'
        results.value = { ...EMPTY, users: [hit(7, 'Budi')] }
        await nextTick()

        option('Budi')?.click()
        await flushPromises()

        expect(visit).toHaveBeenCalledWith('/hasil/7')
        expect(wrapper.props('open')).toBe(false)
    })

    it('Enter pada item yang tersorot membuka tujuannya', async () => {
        await openPalette()
        results.value = { ...EMPTY, submissions: [hit(9, 'Kajian Mangrove')] }
        input().value = 'zz'
        input().dispatchEvent(new Event('input'))
        await flushPromises()

        input().dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }))
        await flushPromises()

        expect(visit).toHaveBeenCalledWith('/hasil/9')
    })

    it('hasil server yang datang setelah mengetik langsung tersorot untuk Enter', async () => {
        await openPalette()
        input().value = 'zz'
        input().dispatchEvent(new Event('input'))
        await flushPromises()

        results.value = { ...EMPTY, users: [hit(4, 'Budi')] }
        await flushPromises()
        input().dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }))
        await flushPromises()

        expect(visit).toHaveBeenCalledWith('/hasil/4')
    })

    it('[K10] fitur ikut dicari, menyebut letaknya, dan membuka route-nya', async () => {
        await openPalette()

        term.value = 'sandi'
        await nextTick()

        expect(groupLabels()).toEqual(['Fitur'])
        const feature = option('Ubah Kata Sandi')
        expect(feature?.querySelector('[data-palette-location]')?.textContent).toBe('Akun')

        feature?.click()
        await flushPromises()
        expect(visit).toHaveBeenCalledWith('/profile.edit')
    })

    it('[K10] fitur di luar izin user tidak pernah muncul', async () => {
        await openPalette()

        term.value = 'formulir'
        await nextTick()

        expect(option('Buat Formulir')).toBeUndefined()
    })

    it('item halaman membuka route-nya', async () => {
        await openPalette()

        option('Beranda')?.click()
        await flushPromises()

        expect(visit).toHaveBeenCalledWith('/dashboard')
        expect(wrapper.props('open')).toBe(false)
    })

    it('istilah dikosongkan saat palette ditutup', async () => {
        await openPalette()
        term.value = 'budi'

        await wrapper.setProps({ open: false })

        expect(term.value).toBe('')
    })
})
