import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    router: { delete: vi.fn() },
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots, attrs }) =>
            () =>
                h('a', { ...attrs, href: props.href }, slots.default?.()),
    }),
}))
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}))

import EvaluationFormIndexPage from '@/Pages/FormEngine/EvaluationFormIndexPage.vue'
import type { EvaluationFormCardData } from '@/types/form-engine'

const card = (overrides: Partial<EvaluationFormCardData> = {}): EvaluationFormCardData => ({
    id: 1,
    title: 'Lembar Proposal',
    latestVersionLabel: 'Versi 2',
    hasDraft: false,
    stepCount: 2,
    assignmentCount: 5,
    ...overrides,
})

const route = (name: string, params?: number) =>
    params === undefined ? `/${name}` : `/${name}/${params}`

const mountPage = (forms: EvaluationFormCardData[]) =>
    mount(EvaluationFormIndexPage, { props: { forms }, global: { mocks: { route } } })

describe('FormEngine/EvaluationFormIndexPage', () => {
    it('judul, subjudul, dan tombol buat', () => {
        const wrapper = mountPage([card()])

        expect(wrapper.text()).toContain('Formulir Evaluasi')
        expect(wrapper.text()).toContain(
            'Lembar penilaian dari penilai yang bisa dipasang di banyak tahap.'
        )
        expect(wrapper.find('a[href="/admin.evaluation-forms.create"]').exists()).toBe(true)
        expect(wrapper.find('a[href="/admin.evaluation-forms.edit/1"]').exists()).toBe(true)
    })

    it('kartu yang belum terbit berlencana "Belum terbit" tanpa label versi', () => {
        const wrapper = mountPage([
            card({ latestVersionLabel: null, stepCount: 0, assignmentCount: 0 }),
        ])

        expect(wrapper.text()).toContain('Belum terbit')
        expect(wrapper.text()).not.toContain('Versi')
    })

    // BR-FE-31 — draf yang lupa diterbitkan terlihat di daftar (§4.5).
    it('banner draf menyebut versi yang masih dipakai reviewer', () => {
        const wrapper = mountPage([
            card({ hasDraft: true }),
            card({ id: 2, title: 'Lembar Baru', latestVersionLabel: null, hasDraft: true }),
        ])

        expect(wrapper.text()).toContain('Draf belum diterbitkan. Penilai masih memakai Versi 2.')
        expect(wrapper.text()).toContain(
            'Draf belum diterbitkan. Belum ada versi yang dipakai penilai.'
        )
    })

    it('kosong menampilkan EmptyState', () => {
        expect(mountPage([]).text()).toContain('Belum ada Formulir Evaluasi.')
    })
})
