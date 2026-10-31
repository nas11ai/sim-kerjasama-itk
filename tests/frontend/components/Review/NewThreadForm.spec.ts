import { mount } from '@vue/test-utils'
import { reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post } = vi.hoisted(() => ({ post: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
    useForm: <T extends object>(data: T) =>
        reactive({ ...data, errors: {}, processing: false, post, reset: vi.fn() }),
}))

import NewThreadForm from '@/Components/Review/NewThreadForm.vue'

const route = (name: string, params: unknown) => `/${name}/${String(params)}`

const mountForm = (canCreate: boolean) =>
    mount(NewThreadForm, { props: { submissionId: 9, canCreate }, global: { mocks: { route } } })

beforeEach(() => {
    vi.stubGlobal('route', route)
    post.mockReset()
})

describe('NewThreadForm', () => {
    it('[REV-EVAL-04] ringkasan tertutup selama evaluasi pending', () => {
        const wrapper = mountForm(false)

        expect(wrapper.text()).toContain(
            'Selesaikan evaluasi Anda sebelum membuat catatan ringkasan.'
        )
        expect(wrapper.find('textarea').exists()).toBe(false)
        expect(wrapper.find('button').attributes('disabled')).toBeDefined()
    })

    it('[REV-DISCUSS-01] membuka ulir lewat review-threads.store', async () => {
        const wrapper = mountForm(true)

        await wrapper.find('textarea').setValue('Perkuat metodologi sampling.')
        await wrapper.find('form').trigger('submit')

        expect(post).toHaveBeenCalledWith(
            '/reviewer.review-threads.store/9',
            expect.objectContaining({ forceFormData: true, preserveScroll: true })
        )
    })
})
