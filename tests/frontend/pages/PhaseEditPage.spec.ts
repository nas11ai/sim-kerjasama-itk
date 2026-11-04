import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { submitted, forms } = vi.hoisted(() => ({
    submitted: vi.fn<(method: string, url: string, data: unknown) => void>(),
    forms: [] as { errors: Record<string, string> }[],
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        useForm: <T extends object>(data: T) => {
            let transformer = (value: T): unknown => value
            const form = reactive({
                ...data,
                errors: {},
                processing: false,
                transform(fn: (value: T) => unknown) {
                    transformer = fn
                    return form
                },
                put(url: string) {
                    submitted('put', url, transformer({ ...data, ...form }))
                },
                post(url: string) {
                    submitted('post', url, transformer({ ...data, ...form }))
                },
            })
            forms.push(form)
            return form
        },
        Head: defineComponent({ setup: () => () => null }),
        Link: defineComponent({
            props: { href: { type: String, required: true } },
            setup:
                (props, { slots, attrs }) =>
                () =>
                    h('a', { ...attrs, href: props.href }, slots.default?.()),
        }),
    }
})
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}))

import PhaseEditPage from '@/Pages/FormEngine/PhaseEditPage.vue'
import type { PhaseDetailEditData, PhaseEditPageProps } from '@/types/form-engine'

const detail = (overrides: Partial<PhaseDetailEditData> = {}): PhaseDetailEditData => ({
    id: 31,
    order: 1,
    formAccessControlId: 41,
    formTitle: 'Form Proposal',
    accessLabel: 'Pengaju · Fakultas Sains',
    phaseTypeId: 5,
    phaseTypeName: 'Isi Proposal',
    needsReview: false,
    submissionDateId: 1,
    evaluationDateId: null,
    revisionDateId: null,
    currentDeadlines: [],
    submissionCount: 0,
    assignmentCount: 0,
    evaluationFormCount: 0,
    ...overrides,
})

const props = (details: PhaseDetailEditData[]): PhaseEditPageProps => ({
    form: { id: 7, title: 'Form Proposal', isActive: true },
    phase: { id: 3, title: 'Tahap Proposal', description: null, isActive: true, details },
    accessOptions: [
        {
            value: '41',
            label: 'Form Proposal — Pengaju — Fakultas Sains',
            formId: 7,
            formTitle: 'Form Proposal',
        },
    ],
    phaseTypeOptions: [{ value: '5', label: 'Isi Proposal' }],
    deadlineOptions: [
        { id: 1, type: 'submit', cycle: null, periodId: 10, label: 'P10 — Batas Submit' },
        { id: 2, type: 'evaluation', cycle: null, periodId: 10, label: 'P10 — Batas Evaluasi' },
    ],
    evaluation: [],
    fieldTypeOptions: [],
})

const route = (name: string, params?: number | Record<string, number>) => {
    if (params === undefined) return `/${name}`
    const ids = typeof params === 'number' ? [params] : Object.values(params)
    return `/${name}/${ids.join('/')}`
}

// Pilihan tenggat dibaca dari prop `options`; Select reka-ui tidak merender isinya di jsdom.
const DeadlineStub = defineComponent({
    props: { options: { type: Array, required: true }, type: { type: String, required: true } },
    setup: (p) => () =>
        h(
            'div',
            { 'data-deadline': p.type },
            p.options.map((option) => JSON.stringify(option))
        ),
})

const mountPage = (pageProps: PhaseEditPageProps) =>
    mount(PhaseEditPage, {
        props: pageProps,
        global: { mocks: { route }, stubs: { PhaseDeadlineSelect: DeadlineStub } },
    })

const openRow = async (wrapper: ReturnType<typeof mountPage>, index: number) => {
    await wrapper
        .findAll('li')
        [index].findAll('button')
        .find((b) => b.text().trim() === 'Edit')
        ?.trigger('click')
    await flushPromises()
}

beforeEach(() => {
    vi.stubGlobal('route', route)
    submitted.mockClear()
    forms.length = 0
    document.body.innerHTML = ''
})

describe('FormEngine/PhaseEditPage', () => {
    it('judul, subjudul, dan back Phases', () => {
        const wrapper = mountPage(props([detail()]))

        expect(wrapper.find('h1').text()).toBe('Edit Phase — Tahap Proposal')
        expect(wrapper.text()).toContain(
            'Susun tahapan pengisian beserta form evaluasi reviewer-nya.'
        )
        expect(wrapper.find('a[href="/admin.forms.phases.index/7"]').text()).toContain('Phases')
        expect(wrapper.findAll('li')[0].text()).toContain('Isi Proposal — Form Proposal')
        expect(wrapper.findAll('li')[0].text()).toContain('Akses: Pengaju · Fakultas Sains')
        expect(wrapper.findAll('li')[0].text()).toContain('Batas Submit: P10 — Batas Submit')
    })

    it('bagian form evaluasi per detail yang direview; Tambah Form Evaluasi membuka kartu baru', async () => {
        const wrapper = mountPage({
            ...props([detail({ needsReview: true })]),
            evaluation: [{ detailId: 31, detailLabel: 'Isi Proposal — Form Proposal', forms: [] }],
        })

        expect(wrapper.text()).toContain('Form evaluasi untuk Isi Proposal — Form Proposal')
        expect(wrapper.text()).toContain('Belum ada form evaluasi.')
        expect(wrapper.text()).toContain(
            'Form evaluasi hanya tersedia pada tahapan reviewer yang ditandai "Perlu Review". Urutan field dapat diubah dengan menyeret.'
        )
        await wrapper
            .findAll('button')
            .find((b) => b.text().trim() === 'Tambah Form Evaluasi')
            ?.trigger('click')

        expect(wrapper.text()).toContain('Form Evaluasi — Baru')
        expect(wrapper.text()).not.toContain('Belum ada form evaluasi.')
    })

    it('[FE-WINDOW-04] dua detail boleh memilih Batas Submit yang sama', async () => {
        const wrapper = mountPage(
            props([detail(), detail({ id: 32, needsReview: true, submissionDateId: null })])
        )

        await openRow(wrapper, 1)

        const submit = document.body.querySelector('[data-deadline="submit"]')
        expect(submit?.textContent).toContain('"id":1')
        expect(document.body.textContent).toContain(
            'Pengajuan dan evaluasinya wajib memakai tanggal berbeda: aktornya berbeda dan tahapannya berurutan.'
        )
    })

    it('[FE-DELETE-07] hapus detail berpengajuan disabled dan menyebut angkanya', () => {
        const wrapper = mountPage(props([detail({ submissionCount: 3 }), detail({ id: 32 })]))
        const removeOf = (index: number) =>
            wrapper
                .findAll('li')
                [index].findAll('button')
                .find((b) => b.text().trim() === 'Hapus')

        expect(removeOf(0)?.attributes('disabled')).toBeDefined()
        expect(wrapper.findAll('li')[0].text()).toContain(
            'Dipakai 3 pengajuan — nonaktifkan tahapnya bila detail ini tidak boleh dipakai lagi.'
        )
        expect(removeOf(1)?.attributes('disabled')).toBeUndefined()
    })

    it('hapus detail yang form evaluasinya pernah ditugaskan disabled (BR-FE-22)', () => {
        const wrapper = mountPage(props([detail({ assignmentCount: 2 })]))

        expect(wrapper.findAll('li')[0].text()).toContain(
            'Form evaluasinya pernah ditugaskan 2 kali.'
        )
    })

    it('[FE-WINDOW-08] galat Batas Evaluasi tampil di baris detailnya', async () => {
        const wrapper = mountPage(props([detail(), detail({ id: 32, needsReview: true })]))

        forms[0].errors = {
            'phase_details.1.evaluation_date_id':
                'Detail yang direview wajib punya Batas Evaluasi.',
        }
        await nextTick()

        expect(wrapper.findAll('li')[1].text()).toContain(
            'Detail yang direview wajib punya Batas Evaluasi.'
        )
        expect(wrapper.findAll('li')[0].text()).not.toContain('wajib punya Batas Evaluasi')
    })

    it('switch Perlu Review disabled selama detail punya form evaluasi aktif (W97)', async () => {
        const wrapper = mountPage(props([detail({ needsReview: true, evaluationFormCount: 2 })]))

        await openRow(wrapper, 0)

        expect(document.body.querySelector('[role="switch"]')?.hasAttribute('disabled')).toBe(true)
        expect(document.body.textContent).toContain('Detail ini punya 2 form evaluasi aktif.')
    })

    it('form evaluasi pertama tersimpan di layar ini langsung mengunci Perlu Review (W97)', async () => {
        const pageProps = props([detail({ needsReview: true, evaluationFormCount: 0 })])
        const wrapper = mountPage(pageProps)
        await wrapper.find('#phase-title').setValue('Judul belum disimpan')

        // Simpan kartu form evaluasi memuat ulang props; PhaseEditor tidak dipasang ulang.
        await wrapper.setProps({
            phase: {
                ...pageProps.phase,
                details: [detail({ needsReview: true, evaluationFormCount: 1 })],
            },
        })
        await openRow(wrapper, 0)

        expect(document.body.querySelector('[role="switch"]')?.hasAttribute('disabled')).toBe(true)
        expect(document.body.textContent).toContain('Detail ini punya 1 form evaluasi aktif.')
        expect(wrapper.find<HTMLInputElement>('#phase-title').element.value).toBe(
            'Judul belum disimpan'
        )
    })

    it('Simpan Tahap mengirim put ke admin.forms.phases.update', async () => {
        const wrapper = mountPage(props([detail()]))
        const saveButton = wrapper.findAll('button').find((b) => b.text().trim() === 'Simpan Tahap')

        expect(saveButton?.attributes('type')).toBe('submit')
        await wrapper.find('form').trigger('submit')

        expect(submitted).toHaveBeenCalledWith(
            'put',
            '/admin.forms.phases.update/7/3',
            expect.objectContaining({
                title: 'Tahap Proposal',
                is_active: true,
                phase_details: [expect.objectContaining({ id: 31, submission_date_id: 1 })],
            })
        )
    })

    describe('mode buat', () => {
        const creating = (accessOptions = props([]).accessOptions): PhaseEditPageProps => ({
            ...props([]),
            phase: { id: null, title: '', description: null, isActive: true, details: [] },
            accessOptions,
        })
        const saveButton = (wrapper: ReturnType<typeof mountPage>) =>
            wrapper.findAll('button').find((b) => b.text().trim() === 'Simpan Tahap')

        it('judul Buat Tahap Baru dan satu detail kosong berakses aturan pertama Form ini', () => {
            const wrapper = mountPage(creating())

            expect(wrapper.find('h1').text()).toBe('Buat Tahap Baru')
            expect(wrapper.findAll('li')).toHaveLength(1)
            expect(wrapper.findAll('li')[0].text()).toContain('Isi Proposal — Form Proposal')
            expect(wrapper.findAll('li')[0].text()).toContain('Baru')
            expect(wrapper.text()).not.toContain('Tambahkan aturan akses Form ini lebih dulu')
        })

        it('Simpan Tahap mengirim post ke admin.forms.phases.store', async () => {
            const wrapper = mountPage(creating())

            await wrapper.find('form').trigger('submit')

            expect(submitted).toHaveBeenCalledWith(
                'post',
                '/admin.forms.phases.store/7',
                expect.objectContaining({
                    phase_details: [expect.objectContaining({ form_access_control_id: 41 })],
                })
            )
        })

        it('[W94] Form tanpa aturan akses menampilkan banner dan menonaktifkan Simpan Tahap', () => {
            const foreign = {
                value: '90',
                label: 'Form Lain — Pengaju — X',
                formId: 8,
                formTitle: 'Form Lain',
            }
            const wrapper = mountPage(creating([foreign]))

            expect(wrapper.text()).toContain(
                'Tambahkan aturan akses Form ini lebih dulu — detail tahap memakai aturan akses sebagai pengisinya.'
            )
            expect(wrapper.find('a[href="/admin.forms.access.index/7"]').exists()).toBe(true)
            expect(wrapper.findAll('li')).toHaveLength(0)
            expect(saveButton(wrapper)?.attributes('disabled')).toBeDefined()
        })
    })
})
