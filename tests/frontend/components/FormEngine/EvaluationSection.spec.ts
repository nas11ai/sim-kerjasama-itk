import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { router } = vi.hoisted(() => ({
    router: { delete: vi.fn(), put: vi.fn(), post: vi.fn() },
}))

vi.mock('@inertiajs/vue3', () => ({
    router,
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots, attrs }) =>
            () =>
                h('a', { ...attrs, href: props.href }, slots.default?.()),
    }),
}))

import OptionSelect from '@/Components/app/OptionSelect.vue'
import EvaluationSection from '@/Components/FormEngine/EvaluationSection.vue'
import type { EvaluationOption, StepEvaluationFormData } from '@/types/form-engine'

const attached = (overrides: Partial<StepEvaluationFormData> = {}): StepEvaluationFormData => ({
    formId: 3,
    title: 'Lembar Substansi',
    versionLabel: 'Versi 2',
    isRequired: true,
    isActive: true,
    ...overrides,
})

const options: EvaluationOption[] = [
    { value: '3', label: 'Lembar Substansi', versionLabel: 'Versi 2' },
    { value: '5', label: 'Lembar Anggaran', versionLabel: 'Versi 1' },
]

const route = (name: string, params?: number) =>
    params === undefined ? `/${name}` : `/${name}/${params}`

let wrapper: VueWrapper

const mountSection = (list: StepEvaluationFormData[]) => {
    wrapper = mount(EvaluationSection, {
        props: {
            modelValue: list,
            'onUpdate:modelValue': (next: StepEvaluationFormData[]) =>
                wrapper.setProps({ modelValue: next }),
            stepKey: 'step-0',
            label: 'Penilaian Proposal: Formulir Proposal',
            evaluationOptions: options,
        },
        global: { mocks: { route } },
    })
    return wrapper
}

const emitted = () => wrapper.emitted('update:modelValue') ?? []
const lastValue = () => emitted().at(-1)?.[0] as StepEvaluationFormData[] | undefined
const button = (label: string) => {
    const found = wrapper.findAll('button').find((b) => b.text().trim() === label)
    if (found === undefined) throw new Error(`tombol "${label}" tidak ada`)
    return found
}

beforeEach(() => vi.stubGlobal('route', route))
afterEach(() => {
    wrapper.unmount()
    vi.clearAllMocks()
})

describe('FormEngine/EvaluationSection', () => {
    it('mendaftar pemasangan dengan label versinya dan menautkan pustaka', () => {
        mountSection([attached({ isRequired: false })])

        expect(wrapper.text()).toContain('Formulir evaluasi untuk Penilaian Proposal: Formulir Proposal')
        expect(wrapper.text()).toContain('Lembar Substansi')
        expect(wrapper.text()).toContain('Versi 2')
        expect(wrapper.text()).toContain('Tidak wajib')
        expect(wrapper.find('a[href="/admin.evaluation-forms.edit/3"]').exists()).toBe(true)
        expect(wrapper.find('a[href="/admin.evaluation-forms.index"]').exists()).toBe(true)
    })

    // BR-FE-30 — hanya Form terbit (pilihan dari backend) yang belum terpasang di langkah ini.
    it('menawarkan hanya Formulir Evaluasi terbit yang belum terpasang', () => {
        mountSection([attached()])

        expect(wrapper.getComponent(OptionSelect).props('options')).toEqual([
            { value: '5', label: 'Lembar Anggaran', versionLabel: 'Versi 1' },
        ])
    })

    // BR-FE-33 — memasang hanya mengubah isi draf; tersimpan lewat Simpan draf.
    it('Pasang menambah pemasangan ke draf tanpa request', async () => {
        mountSection([])

        wrapper.getComponent(OptionSelect).vm.$emit('update:modelValue', '5')
        await flushPromises()
        await button('Pasang').trigger('click')

        expect(lastValue()).toEqual([
            {
                formId: 5,
                title: 'Lembar Anggaran',
                versionLabel: 'Versi 1',
                isRequired: true,
                isActive: true,
            },
        ])
        expect(router.post).not.toHaveBeenCalled()
    })

    it('ubah wajib, nonaktifkan, dan lepas mengubah draf tanpa request', async () => {
        mountSection([attached(), attached({ formId: 5, title: 'Lembar Anggaran' })])

        await wrapper
            .findAll('li')[0]
            .findAll('button')
            .find((b) => b.text() === 'Jadikan tidak wajib')
            ?.trigger('click')
        expect(lastValue()?.[0]).toMatchObject({ formId: 3, isRequired: false, isActive: true })

        await wrapper
            .findAll('li')[0]
            .findAll('button')
            .find((b) => b.text() === 'Nonaktifkan')
            ?.trigger('click')
        expect(lastValue()?.[0]).toMatchObject({ formId: 3, isRequired: false, isActive: false })

        await wrapper
            .findAll('li')[1]
            .findAll('button')
            .find((b) => b.text().trim() === 'Lepas')
            ?.trigger('click')
        expect(lastValue()?.map((s) => s.formId)).toEqual([3])

        expect(router.put).not.toHaveBeenCalled()
        expect(router.delete).not.toHaveBeenCalled()
    })

    it('pemasangan yang Form-nya sudah dihapus tetap bisa dilepas', () => {
        mountSection([attached({ title: '', versionLabel: '' })])

        expect(wrapper.text()).toContain('Formulir evaluasi sudah dihapus dari pustaka')
        expect(wrapper.find('a[href="/admin.evaluation-forms.edit/3"]').exists()).toBe(false)
        expect(button('Lepas').exists()).toBe(true)
    })
})
