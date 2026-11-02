import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))
vi.mock('@/Layouts/AuthLayout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}))

import VerificationStatusPage from '@/Pages/Auth/VerificationStatusPage.vue'
import type { AccountVerification } from '@/types/auth'

const verification = (overrides: Partial<AccountVerification> = {}): AccountVerification => ({
    status: 'rejected',
    statusLabel: 'Ditolak',
    nidn: '0543210987',
    email: 'hasan.basri@itk.ac.id',
    rejectionReason: 'NIDN tidak cocok.',
    rejectedAt: '2026-07-03T08:00:00+08:00',
    ...overrides,
})

const mountPage = (overrides: Partial<AccountVerification> = {}) =>
    mount(VerificationStatusPage, {
        props: { verification: verification(overrides) },
        global: { mocks: { route: (name: string) => `/${name}` } },
    })

describe('VerificationStatusPage', () => {
    it('[IAM-VERIFY-07] akun ditolak membaca "Ditolak pada" (W5)', () => {
        const text = mountPage().text()

        expect(text).toContain('Ditolak pada')
        expect(text).toContain('03 Jul 2026')
    })

    it('akun pending tidak menampilkan "Ditolak pada"', () => {
        const text = mountPage({
            status: 'pending',
            rejectionReason: null,
            rejectedAt: null,
        }).text()

        expect(text).not.toContain('Ditolak pada')
    })
})
