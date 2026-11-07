import { reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

type Payload = Record<string, unknown>

const { submitted } = vi.hoisted(() => ({ submitted: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
    useForm: <T extends object>(data: T) => {
        let transform = (payload: Payload): Payload => payload
        const form = reactive({
            ...data,
            errors: {} as Record<string, string>,
            processing: false,
            transform(fn: (payload: Payload) => Payload) {
                transform = fn
                return form
            },
            post(url: string, options: Payload) {
                submitted(url, transform(fields()), options)
            },
        })
        const fields = (): Payload =>
            Object.fromEntries(Object.keys(data).map((key) => [key, Reflect.get(form, key)]))
        return form
    },
}))

import { useOutputForm } from '@/composables/useOutputForm'
import type {
    OutputFieldSpec,
    OutputType,
    ResearchOutputFormPageProps,
} from '@/types/research-output'

const field = (key: string, required: boolean, type: OutputFieldSpec['type'] = 'text') => ({
    key,
    label: key,
    type,
    required,
    options: [],
    hint: null,
    emptyLabel: null,
})

const types: OutputType[] = [
    {
        code: 'article',
        name: 'Artikel Jurnal',
        requiresFiles: false,
        fields: [field('title', true)],
    },
    { code: 'ip', name: 'HKI', requiresFiles: true, fields: [field('title', true)] },
    { code: 'prototype', name: 'Prototipe', requiresFiles: true, fields: [field('name', true)] },
    {
        code: 'pks',
        name: 'PKS',
        requiresFiles: false,
        fields: [field('start_date', true, 'date'), field('end_date', false, 'date')],
    },
]

const props = (over: Partial<ResearchOutputFormPageProps> = {}): ResearchOutputFormPageProps => ({
    submissionId: 7,
    submissionTitle: 'Smart Farming Hidroponik Kampus',
    scheme: 'Penelitian Mandiri',
    access: { canWrite: true, blockedReason: null, deadline: null, overrideUntil: null },
    types,
    outputId: null,
    outputType: null,
    metadata: {},
    files: [],
    ...over,
})

const pdf = () => new File(['x'], 'bukti.pdf', { type: 'application/pdf' })

beforeEach(() => {
    vi.stubGlobal('route', (name: string, params: unknown) => `/${name}/${String(params)}`)
    submitted.mockReset()
})

describe('useOutputForm', () => {
    it('tombol simpan mati sebelum tipe dipilih', () => {
        expect(useOutputForm(props()).canSave.value).toBe(false)
    })

    it('[RO-OUTPUT-06] tombol simpan mati untuk HKI tanpa berkas', () => {
        const output = useOutputForm(props())
        output.changeType('ip')
        output.form.metadata.title = 'Paten'

        expect(output.canSave.value).toBe(false)

        output.form.files = [pdf()]
        expect(output.canSave.value).toBe(true)
    })

    it('[RO-OUTPUT-17] tombol simpan mati untuk prototipe tanpa berkas', () => {
        const output = useOutputForm(props())
        output.changeType('prototype')
        output.form.metadata.name = 'Modul Kontrol'

        expect(output.canSave.value).toBe(false)
    })

    it('[RO-OUTPUT-21] tombol simpan mati selama Judul Artikel kosong', () => {
        const output = useOutputForm(props())
        output.changeType('article')
        output.form.metadata.title = '   '

        expect(output.canSave.value).toBe(false)

        output.form.metadata.title = 'Optimasi Nutrisi'
        expect(output.canSave.value).toBe(true)
    })

    it('[RO-OUTPUT-22] end_date PKS kosong tidak menahan simpan', () => {
        const output = useOutputForm(props())
        output.changeType('pks')
        output.form.metadata.start_date = '2026-02-01'

        expect(output.canSave.value).toBe(true)
    })

    it('berkas lama dihitung saat sunting', () => {
        const output = useOutputForm(
            props({
                outputId: 3,
                outputType: 'ip',
                metadata: { title: 'Paten' },
                files: [{ id: 1, name: 'HKI.pdf', size: 1024, url: '/f/1' }],
            })
        )

        expect(output.canSave.value).toBe(true)
    })

    it('ganti tipe mengosongkan isian', () => {
        const output = useOutputForm(
            props({ outputId: 3, outputType: 'article', metadata: { title: 'A' } })
        )
        output.form.files = [pdf()]

        output.changeType('book')

        expect(output.form.output_type).toBe('book')
        expect(output.form.metadata).toEqual({})
        expect(output.form.files).toEqual([])
        expect(output.definition.value).toBeNull()
    })

    it('tambah dikirim ke store, sunting ke update dengan _method put', () => {
        const create = useOutputForm(props())
        create.changeType('article')
        create.submit()

        expect(submitted).toHaveBeenLastCalledWith(
            '/research-outputs.store/7',
            { output_type: 'article', metadata: {}, files: [] },
            expect.objectContaining({ forceFormData: true })
        )

        useOutputForm(props({ outputId: 3, outputType: 'article' })).submit()

        expect(submitted).toHaveBeenLastCalledWith(
            '/research-outputs.update/3',
            expect.objectContaining({ _method: 'put', output_type: 'article' }),
            expect.objectContaining({ forceFormData: true })
        )
    })
})
