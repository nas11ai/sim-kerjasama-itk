import { flushPromises, mount } from '@vue/test-utils'
import { reactive } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { router, submitted } = vi.hoisted(() => ({
    router: { reload: vi.fn() },
    submitted: vi.fn(),
}))

vi.mock('@inertiajs/vue3', () => ({
    router,
    useForm: <T extends object>(data: T) => {
        const form = reactive({
            ...data,
            errors: {} as Record<string, string>,
            processing: false,
            reset: vi.fn(),
            clearErrors: vi.fn(),
            post(url: string) {
                submitted(
                    'post',
                    url,
                    Object.fromEntries(
                        Object.keys(data).map((key) => [key, Reflect.get(form, key)])
                    )
                )
            },
        })
        return form
    },
}))

import OptionSelect from '@/Components/app/OptionSelect.vue'
import MoveUserDialog from '@/Components/SystemConfig/MoveUserDialog.vue'
import type { MemberCandidate } from '@/types/system-config'

const candidates: MemberCandidate[] = [
    { value: '7', label: 'Budi Santoso', hint: 'Informatika · Aktif', organizationId: 3 },
    { value: '8', label: 'Sari Dewi', hint: 'Informatika · Nonaktif', organizationId: 3 },
]

const route = (name: string, id?: number | string) =>
    id === undefined ? `/${name}` : `/${name}/${id}`

const mountDialog = async () => {
    const wrapper = mount(MoveUserDialog, {
        props: {
            open: true,
            candidates,
            parentOptions: [
                { value: '2', label: 'FTI — beserta unit di bawahnya' },
                { value: '3', label: 'Informatika' },
                { value: '5', label: 'Fakultas Sains' },
            ],
        },
        global: { mocks: { route } },
        attachTo: document.body,
    })
    await flushPromises()
    return wrapper
}

const bodyButton = (text: string) =>
    Array.from(document.querySelectorAll('button')).find((b) => b.textContent?.includes(text))

beforeEach(() => {
    vi.stubGlobal('route', route)
    document.body.innerHTML = ''
    router.reload.mockClear()
    submitted.mockClear()
})

afterEach(() => vi.useRealTimers())

describe('MoveUserDialog', () => {
    it('[IAM-ORG-14] memindahkan mengirim user_id ke unit tujuan', async () => {
        const wrapper = await mountDialog()

        bodyButton('Budi Santoso')?.click()
        await flushPromises()
        const target = wrapper.findComponent(OptionSelect)
        // Unit asal user tidak ditawarkan sebagai tujuan.
        expect(target.props('options').map((o: { value: string }) => o.value)).toEqual(['2', '5'])

        target.vm.$emit('update:modelValue', '5')
        await flushPromises()
        document.querySelector<HTMLFormElement>('form')?.requestSubmit()
        await flushPromises()

        expect(submitted).toHaveBeenCalledWith('post', '/admin.organizations.move-user/5', {
            user_id: 7,
        })
    })

    it('pencarian memuat ulang memberCandidates saja', async () => {
        vi.useFakeTimers()
        await mountDialog()
        router.reload.mockClear()

        const input = document.querySelector<HTMLInputElement>('input[type="search"]')
        if (input === null) throw new Error('kolom cari tidak ada')
        input.value = 'bud'
        input.dispatchEvent(new Event('input'))
        await vi.runAllTimersAsync()

        expect(router.reload).toHaveBeenCalledWith({
            only: ['memberCandidates'],
            data: { member_search: 'bud' },
        })
    })
})
