import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import AppStepper from '@/Components/app/AppStepper.vue'
import type { WizardStep } from '@/types/submission-wizard'

const labels = [
    'Pilih Formulir & Periode',
    'Informasi Dasar',
    'Skema, TKT & Target Luaran',
    'Anggota Penelitian',
    'Rencana Anggaran',
    'Unggah Berkas',
    'Periksa & Kirim',
]

const reason = 'Pilih skema di langkah 3 lebih dulu, batas anggaran berasal dari skema.'

const steps: WizardStep[] = labels.map((label, index) => ({
    number: index + 1,
    label,
    completed: index < 3,
    blockedReason: index === 4 ? reason : null,
}))

const mountStepper = (active: number, disabled = false) =>
    mount(AppStepper, { props: { steps, active, disabled } })

const button = (wrapper: ReturnType<typeof mountStepper>, number: number) =>
    wrapper.findAll('button')[number - 1]

describe('AppStepper', () => {
    it('menandai langkah selesai', () => {
        const wrapper = mountStepper(4)

        expect(button(wrapper, 2).attributes('data-status')).toBe('completed')
        expect(button(wrapper, 4).attributes('data-status')).toBe('active')
        expect(button(wrapper, 4).attributes('aria-current')).toBe('step')
        expect(button(wrapper, 6).attributes('data-status')).toBe('idle')
    })

    it('[SM-WIZARD-04] langkah terkunci tetap bisa dipilih dan menyebut alasannya', async () => {
        const wrapper = mountStepper(3)
        const locked = button(wrapper, 5)

        expect(locked.attributes('data-status')).toBe('blocked')
        expect(locked.attributes('title')).toBe(reason)
        expect(locked.attributes('disabled')).toBeUndefined()

        await locked.trigger('click')

        expect(wrapper.emitted('select')).toEqual([[5]])
    })

    it('[SM-WIZARD-10] memancarkan select untuk langkah sebelumnya', async () => {
        const wrapper = mountStepper(6)

        await button(wrapper, 2).trigger('click')

        expect(wrapper.emitted('select')).toEqual([[2]])
    })

    it('disabled hanya menyisakan langkah aktif', () => {
        const wrapper = mountStepper(2, true)
        const enabled = wrapper
            .findAll('button')
            .filter((b) => b.attributes('disabled') === undefined)

        expect(enabled).toHaveLength(1)
        expect(enabled[0].text()).toContain('Informasi Dasar')
    })
})
