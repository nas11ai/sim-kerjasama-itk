import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { posted, deleted } = vi.hoisted(() => ({
    posted: vi.fn<(url: string, data: Record<string, unknown>) => void>(),
    deleted: vi.fn<(url: string) => void>(),
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        useForm: <T extends object>(data: T) => {
            const form = reactive({
                ...data,
                errors: {} as Record<string, string>,
                processing: false,
                post(url: string) {
                    posted(url, { ...form })
                },
                reset() {},
            })
            return form
        },
        router: {
            delete: (url: string, options?: { onSuccess?: () => void }) => {
                deleted(url)
                options?.onSuccess?.()
            },
        },
        Link: { template: '<a><slot /></a>' },
    }
})

import FormAccessTab from '@/Components/FormEngine/FormAccessTab.vue'
import type { FormAccessRuleData } from '@/types/form-engine'

const route = (name: string, params?: number | Record<string, number>) =>
    [`/${name}`, typeof params === 'object' ? Object.values(params).join('/') : params]
        .filter((part) => part !== undefined)
        .join('/')

const rules: FormAccessRuleData[] = [
    {
        id: 1,
        permission: 'submissions.create',
        permissionLabel: 'Membuat pengajuan',
        permissionDescription: 'Membuat dan mengirim pengajuan baru.',
        organizationId: 5,
        organizationLabel: 'FTI — beserta unit di bawahnya',
        phaseCount: 0,
    },
    {
        id: 2,
        permission: 'submissions.view-own',
        permissionLabel: 'Melihat pengajuan sendiri',
        permissionDescription: 'Melihat pengajuan yang dibuat sendiri.',
        organizationId: 9,
        organizationLabel: 'Pusat Studi Energi',
        phaseCount: 2,
    },
]

const mountTab = () =>
    mount(FormAccessTab, {
        props: {
            form: { id: 7, title: 'Form Proposal', isActive: true },
            rules,
            permissionOptions: [
                {
                    value: 'submissions.create',
                    label: 'Membuat pengajuan',
                    description: 'Membuat dan mengirim pengajuan baru.',
                },
            ],
            organizationOptions: [{ value: '5', label: 'FTI — beserta unit di bawahnya' }],
        },
        attachTo: document.body,
        global: { mocks: { route } },
    })

const buttonByText = (wrapper: ReturnType<typeof mountTab>, label: string) =>
    wrapper.findAll('button').filter((b) => b.text().trim() === label)

beforeEach(() => {
    vi.stubGlobal('route', route)
    posted.mockClear()
    deleted.mockClear()
    document.body.innerHTML = ''
})

describe('FormEngine/FormAccessTab', () => {
    it('[K5] hak akses tampil berlabel; kodenya hanya di tooltip', () => {
        const wrapper = mountTab()

        expect(wrapper.text()).toContain('Membuat pengajuan')
        expect(wrapper.find('code').exists()).toBe(false)
        expect(wrapper.find('[aria-label="Penjelasan: Membuat pengajuan"]').exists()).toBe(true)
        // Penjelasan permission terpilih tampil di bawah pilihan.
        expect(wrapper.text()).toContain('Membuat dan mengirim pengajuan baru.')
    })

    it('hapus disabled bagi aturan yang dipakai tahap terbit', () => {
        const wrapper = mountTab()
        const buttons = buttonByText(wrapper, 'Hapus')

        expect(buttons).toHaveLength(2)
        expect(buttons[0]?.attributes('disabled')).toBeUndefined()
        expect(buttons[1]?.attributes('disabled')).toBeDefined()
        expect(wrapper.text()).toContain('Dipakai 2 tahap')
    })

    it('menambah aturan memanggil route store', async () => {
        const wrapper = mountTab()

        await wrapper.find('form').trigger('submit')

        expect(posted).toHaveBeenCalledTimes(1)
        expect(posted.mock.calls[0]?.[0]).toBe('/admin.forms.access.store/7')
        expect(posted.mock.calls[0]?.[1]).toMatchObject({ permission: 'submissions.create' })
    })

    it('hapus dikonfirmasi lalu memanggil route destroy', async () => {
        const wrapper = mountTab()

        await buttonByText(wrapper, 'Hapus')[0]?.trigger('click')
        await flushPromises()
        expect(deleted).not.toHaveBeenCalled()
        expect(document.body.textContent).toContain('Hapus aturan akses?')

        const confirm = Array.from(document.body.querySelectorAll('button')).find(
            (b) => b.textContent?.trim() === 'Hapus Aturan'
        )
        confirm?.click()
        await flushPromises()

        expect(deleted).toHaveBeenCalledWith('/admin.forms.access.destroy/7/1')
    })
})
