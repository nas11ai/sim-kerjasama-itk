import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

type Visit = { onSuccess?: () => void; onError?: () => void; onFinish?: () => void }
type FakeForm = { errors: Record<string, string>; [key: string]: unknown }

const { post, destroy } = vi.hoisted(() => ({
    post: vi.fn<(form: FakeForm, url: string, options: Visit) => void>(),
    destroy: vi.fn(),
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        router: { delete: destroy },
        useForm: <T extends object>(data: T) => {
            const form = reactive({
                ...data,
                errors: {},
                processing: false,
                reset: vi.fn(),
                post: (url: string, options: Visit) => post(form, url, options),
            })
            return form
        },
    }
})

import ExtendDeadlineDialog from '@/Components/Submission/Manage/ExtendDeadlineDialog.vue'
import type { OverrideTarget } from '@/types/submission-manage'
import type { PhaseAccess } from '@/types/submission-page'

const route = (name: string, params: unknown) => `/${name}/${String(params)}`

const closedPhase: PhaseAccess = {
    is_open: false,
    deadline: '2026-03-20T23:59:59+08:00',
    form_phase_detail_id: 3,
    override: null,
}

const target: OverrideTarget = {
    phaseLabel: 'Pengajuan: Batas Pengiriman',
    affectedReviewers: ['Sari', 'Andi'],
}

const text = () => document.body.textContent ?? ''

const button = (label: string) => {
    const found = Array.from(document.querySelectorAll('button')).find(
        (b) => b.textContent?.trim() === label
    )
    if (found === undefined) {
        throw new Error(`tombol "${label}" tidak ada`)
    }
    return found
}

const fill = async (selector: string, value: string) => {
    const field = document.querySelector<HTMLInputElement | HTMLTextAreaElement>(selector)
    if (field === null) {
        throw new Error(`field ${selector} tidak ada`)
    }
    field.value = value
    field.dispatchEvent(new Event('input'))
    await flushPromises()
}

const click = async (label: string) => {
    button(label).click()
    await flushPromises()
}

let wrapper: VueWrapper

const open = async (phaseAccess: PhaseAccess = closedPhase, overrideTarget = target) => {
    wrapper = mount(ExtendDeadlineDialog, {
        props: { open: true, submissionId: 9, phaseAccess, target: overrideTarget },
        attachTo: document.body,
        global: { mocks: { route } },
    })
    await flushPromises()
}

beforeEach(() => {
    vi.stubGlobal('route', route)
    post.mockReset()
    destroy.mockReset()
})

afterEach(() => wrapper.unmount())

describe('ExtendDeadlineDialog', () => {
    it('[FE-OVERRIDE-03] perpanjangan dikirim berlaku sampai akhir hari', async () => {
        await open()

        expect(text()).toContain('Pengajuan: Batas Pengiriman')
        expect(text()).toContain('terlewat')
        await fill('#extend-date', '2026-12-31')
        await fill('#extend-reason', 'Penilai sakit dua minggu.')
        await click('Perpanjang Tenggat')

        const [form, url] = post.mock.calls[0]
        expect(url).toBe('/admin.submissions.overrides.store/9')
        expect(form.expires_at).toBe('2026-12-31 23:59:59')
        expect(form.form_phase_detail_id).toBe(3)
        expect(form.reason).toBe('Penilai sakit dua minggu.')
    })

    it('[FE-OVERRIDE-06] alasan kosong tampil sebagai galat field', async () => {
        post.mockImplementation((form, _url, options) => {
            form.errors = { reason: 'Alasan perpanjangan wajib diisi.' }
            options.onError?.()
        })
        await open()

        await fill('#extend-date', '2026-12-31')
        await click('Perpanjang Tenggat')

        expect(text()).toContain('Alasan perpanjangan wajib diisi.')
        expect(wrapper.emitted('update:open')).toBeUndefined()
    })

    it('[FE-OVERRIDE-05] override aktif bisa dicabut lewat konfirmasi', async () => {
        await open({
            ...closedPhase,
            override: {
                id: 4,
                status: 'active',
                expires_at: '2026-03-31T23:59:59+08:00',
                reason: 'Penilai sakit.',
            },
        })

        expect(text()).toContain('Aktif sampai')
        expect(text()).toContain('Penilai sakit.')
        await click('Cabut')
        expect(destroy).not.toHaveBeenCalled()

        await click('Cabut Perpanjangan')

        expect(destroy).toHaveBeenCalledWith(
            '/admin.submissions.overrides.destroy/9,4',
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('[FE-WINDOW-05] reviewer terdampak ditampilkan', async () => {
        await open()

        expect(text()).toContain('Penilai Terdampak')
        expect(text()).toContain('Sari, Andi')
    })

    it('tanpa tahap tidak menawarkan form', async () => {
        await open(
            { ...closedPhase, form_phase_detail_id: null },
            { phaseLabel: null, affectedReviewers: [] }
        )

        expect(text()).toContain('Pengajuan ini tidak tertaut ke tahap mana pun')
        expect(document.querySelector('#extend-date')).toBeNull()
        expect(text()).not.toContain('Penilai Terdampak')
    })
})
