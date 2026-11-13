import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))

import AssignmentCard from '@/Components/Review/AssignmentCard.vue'
import type { AssignmentCard as Card } from '@/types/review'

const card: Card = {
    submissionId: 9,
    title: 'Analisis Potensi Biochar',
    leadName: 'Dr. Budi Santoso',
    scheme: 'Penelitian Dasar',
    dueDate: '2026-11-03T00:00:00.000000Z',
    state: 'evaluation_pending',
    stateLabel: 'Evaluasi Pending',
}

const route = (name: string, params?: unknown) =>
    params === undefined ? `/${name}` : `/${name}/${String(params)}`

const mountCard = (overrides: Partial<Card> = {}) =>
    mount(AssignmentCard, {
        props: { card: { ...card, ...overrides } },
        global: { mocks: { route } },
    })

beforeEach(() => vi.stubGlobal('route', route))

describe('AssignmentCard', () => {
    it('evaluasi pending menaut ke halaman evaluasi', () => {
        const wrapper = mountCard()

        expect(wrapper.text()).toContain('Dr. Budi Santoso · Penelitian Dasar')
        expect(wrapper.text()).toContain('Tenggat evaluasi:')
        expect(wrapper.text()).toContain('Evaluasi Pending')
        const action = wrapper.find('a')
        expect(action.text()).toContain('Mulai Evaluasi')
        expect(action.attributes('href')).toBe('/reviewer.assignments.evaluation/9')
    })

    it('ulir terbuka menawarkan Buka Diskusi', () => {
        const wrapper = mountCard({ state: 'discussion_open', stateLabel: 'Butuh Respon Diskusi' })

        expect(wrapper.find('a').text()).toContain('Buka Diskusi')
        expect(wrapper.find('a').attributes('href')).toBe('/reviewer.assignments.summary/9')
    })

    it('tanpa tenggat baris deadline tidak dirender', () => {
        expect(mountCard({ dueDate: null }).text()).not.toContain('Tenggat evaluasi')
    })
})
