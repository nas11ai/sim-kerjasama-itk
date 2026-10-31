import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post, page } = vi.hoisted(() => ({
    post: vi.fn(),
    page: { props: { errors: {} as Record<string, string> } },
}))

vi.mock('@inertiajs/vue3', () => ({ router: { post }, usePage: () => page }))

import { useWizardStep } from '@/composables/useWizardStep'
import type { WizardField, WizardSubmission } from '@/types/submission-wizard'

const submission: WizardSubmission = {
    id: 7,
    title: 'Form Proposal',
    status: { key: 'draft', label: 'Draft', variant: 'secondary' },
    period: 'DIPA 2026',
    form: 'Form Proposal',
    lastUpdatedAt: '2026-10-31T01:00:00.000000Z',
}

const field = (id: number, type: WizardField['type'], value: string | null): WizardField => ({
    id,
    label: `Field ${id}`,
    type,
    required: false,
    value,
    options: [],
    fileName: type === 'file' ? 'proposal.pdf' : null,
    downloadUrl: null,
})

const fields = [field(1, 'text', 'Judul A'), field(2, 'textarea', null), field(3, 'file', null)]

const lastCall = () => post.mock.calls[post.mock.calls.length - 1]

beforeEach(() => {
    vi.stubGlobal(
        'route',
        (name: string, params: unknown) => `/${name}/${[params].flat().join(',')}`
    )
    post.mockReset()
    page.props.errors = {}
})

describe('useWizardStep', () => {
    it('mengirim seluruh field non-berkas langkah ini', () => {
        const step = useWizardStep(submission, 2, fields)
        step.values[1] = 'Judul B'

        step.go(3)

        expect(lastCall()[0]).toBe('/user.submissions.wizard.save/7,2')
        expect(lastCall()[1].responses).toEqual([
            { form_field_id: 1, value: 'Judul B' },
            { form_field_id: 2, value: null },
        ])
    })

    it('tidak mengirim field berkas tanpa berkas baru', () => {
        const step = useWizardStep(submission, 6, fields)

        step.go(7)

        const sent = lastCall()[1].responses.map((r: { form_field_id: number }) => r.form_field_id)
        expect(sent).not.toContain(3)
        expect(lastCall()[2].forceFormData).toBe(false)
    })

    it('memakai FormData bila ada berkas', () => {
        const step = useWizardStep(submission, 6, fields)
        const file = new File(['isi'], 'proposal.pdf', { type: 'application/pdf' })
        step.values[3] = file

        step.go(7)

        expect(lastCall()[2].forceFormData).toBe(true)
        expect(lastCall()[1].responses).toContainEqual({ form_field_id: 3, value: file })
    })

    it('string kosong dikirim sebagai null', () => {
        const step = useWizardStep(submission, 2, fields)
        step.values[1] = ''

        step.go(3)

        expect(lastCall()[1].responses[0]).toEqual({ form_field_id: 1, value: null })
    })

    it('membawa goto dan last_updated_at', () => {
        useWizardStep(submission, 2, fields).go(5)

        expect(lastCall()[1]).toMatchObject({ goto: 5, last_updated_at: submission.lastUpdatedAt })
        expect(lastCall()[2].preserveScroll).toBe(true)
        // Isian yang belum tersimpan bertahan bila backend menolak; berhasil = langkah baru.
        expect(lastCall()[2].preserveState).toBe('errors')
    })

    it('menerjemahkan error berkas responses.{i}.value ke field_{id}', () => {
        const step = useWizardStep(submission, 6, fields)
        step.values[3] = new File(['isi'], 'besar.pdf')
        step.go(7)
        page.props.errors = { 'responses.2.value': 'Ukuran file terlalu besar. Maksimal 10MB.' }

        expect(step.errors.value).toEqual({ field_3: 'Ukuran file terlalu besar. Maksimal 10MB.' })
    })
})
