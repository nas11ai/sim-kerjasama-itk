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

import OptionSelect from '@/Components/app/OptionSelect.vue'
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
    deadlineType: 'submit',
    deadlineCycle: null,
    deadlineLabel: 'Pengajuan',
    submissionCount: 0,
    assignmentCount: 0,
    evaluationFormCount: 0,
    ...overrides,
})

const props = (details: PhaseDetailEditData[]): PhaseEditPageProps => ({
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
    evaluation: [],
    evaluationOptions: [],
    returnForm: null,
})

const route = (name: string, params?: number | Record<string, number>) => {
    if (params === undefined) return `/${name}`
    const ids = typeof params === 'number' ? [params] : Object.values(params)
    return `/${name}/${ids.join('/')}`
}

const mountPage = (pageProps: PhaseEditPageProps) =>
    mount(PhaseEditPage, { props: pageProps, global: { mocks: { route } } })

// Select reka-ui tidak merender isinya di jsdom; pilihan dikirim lewat event OptionSelect.
const chooseStepKind = async (wrapper: ReturnType<typeof mountPage>, value: string) => {
    const select = wrapper
        .findAllComponents(OptionSelect)
        .find((c) => c.props('id') === 'detail-kind')
    select?.vm.$emit('update:modelValue', value)
    await flushPromises()
}

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
    it('judul, subjudul, dan back ke pustaka Fase', () => {
        const wrapper = mountPage(props([detail()]))

        expect(wrapper.find('h1').text()).toBe('Edit Phase — Tahap Proposal')
        expect(wrapper.text()).toContain(
            'Susun tahapan pengisian beserta form evaluasi reviewer-nya.'
        )
        expect(wrapper.find('a[href="/admin.phases.index"]').text()).toContain('Fase')
        expect(
            wrapper
                .findAll('a')
                .find((a) => a.text() === 'Batalkan')
                ?.attributes('href')
        ).toBe('/admin.phases.index')
        expect(wrapper.findAll('li')[0].text()).toContain('Isi Proposal — Form Proposal')
        expect(wrapper.findAll('li')[0].text()).toContain('Akses: Pengaju · Fakultas Sains')
        expect(wrapper.findAll('li')[0].text()).toContain('Jenis: Pengajuan')
    })

    it('bagian form evaluasi per detail yang direview memasang Form Evaluasi pustaka (BR-FE-30)', () => {
        const wrapper = mountPage({
            ...props([detail({ needsReview: true })]),
            evaluation: [
                {
                    detailId: 31,
                    detailLabel: 'Isi Proposal — Form Proposal',
                    attached: [],
                    reviewsInProgress: 0,
                },
            ],
            evaluationOptions: [{ value: '3', label: 'Lembar Substansi' }],
        })

        expect(wrapper.text()).toContain('Form evaluasi untuk Isi Proposal — Form Proposal')
        expect(wrapper.text()).toContain('Belum ada form evaluasi terpasang.')
        expect(wrapper.text()).toContain('Pasang Form Evaluasi')
        expect(wrapper.text()).toContain('Kelola di Form Evaluasi')
    })

    it('daftar langkah menampilkan jenis langkahnya', () => {
        const wrapper = mountPage(
            props([detail({ id: 32, deadlineCycle: 2, deadlineLabel: 'Laporan Monev II' })])
        )

        expect(wrapper.findAll('li')[0].text()).toContain('Jenis: Laporan Monev II')
    })

    it('[FE-WINDOW-09] memilih Upload Luaran mematikan Perlu Review', async () => {
        const wrapper = mountPage(props([detail({ needsReview: true })]))

        await openRow(wrapper, 0)
        await chooseStepKind(wrapper, 'output:')

        const toggle = document.body.querySelector('[role="switch"]')
        expect(toggle?.hasAttribute('disabled')).toBe(true)
        expect(toggle?.getAttribute('aria-checked')).toBe('false')
        expect(document.body.textContent).toContain('Detail Upload Luaran tidak direview.')
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

    it('galat jenis langkah tampil di baris detailnya', async () => {
        const wrapper = mountPage(props([detail(), detail({ id: 32 })]))

        forms[0].errors = { 'phase_details.1.deadline_cycle': 'Upload Luaran tidak bersiklus.' }
        await nextTick()

        expect(wrapper.findAll('li')[1].text()).toContain('Upload Luaran tidak bersiklus.')
        expect(wrapper.findAll('li')[0].text()).not.toContain('tidak bersiklus')
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

    it('Simpan Tahap mengirim put ke admin.phases.update', async () => {
        const wrapper = mountPage(props([detail()]))
        const saveButton = wrapper.findAll('button').find((b) => b.text().trim() === 'Simpan Tahap')

        expect(saveButton?.attributes('type')).toBe('submit')
        await wrapper.find('form').trigger('submit')

        expect(submitted).toHaveBeenCalledWith(
            'put',
            '/admin.phases.update/3',
            expect.objectContaining({
                form: null,
                title: 'Tahap Proposal',
                is_active: true,
                phase_details: [
                    expect.objectContaining({
                        id: 31,
                        deadline_type: 'submit',
                        deadline_cycle: null,
                    }),
                ],
            })
        )
    })

    it('[B5] dari pustaka bertapis: Kembali, Batalkan, dan simpan membawa Form tapisnya', async () => {
        const wrapper = mountPage({ ...props([detail()]), returnForm: 7 })

        expect(wrapper.find('a[href="/admin.phases.index/7"]').text()).toContain('Fase')
        expect(
            wrapper
                .findAll('a')
                .find((a) => a.text() === 'Batalkan')
                ?.attributes('href')
        ).toBe('/admin.phases.index/7')
        await wrapper.find('form').trigger('submit')
        expect(submitted).toHaveBeenCalledWith(
            'put',
            '/admin.phases.update/3',
            expect.objectContaining({ form: 7 })
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

        it('judul Buat Tahap Baru dan satu detail Pengajuan kosong berakses pilihan pertama', () => {
            const wrapper = mountPage(creating())

            expect(wrapper.find('h1').text()).toBe('Buat Tahap Baru')
            expect(wrapper.findAll('li')).toHaveLength(1)
            expect(wrapper.findAll('li')[0].text()).toContain('Isi Proposal — Form Proposal')
            expect(wrapper.findAll('li')[0].text()).toContain('Baru')
            expect(wrapper.findAll('li')[0].text()).toContain('Jenis: Pengajuan')
            expect(wrapper.text()).not.toContain('Belum ada Form aktif yang punya aturan akses.')
        })

        it('Simpan Tahap mengirim post ke admin.phases.store', async () => {
            const wrapper = mountPage(creating())

            await wrapper.find('form').trigger('submit')

            expect(submitted).toHaveBeenCalledWith(
                'post',
                '/admin.phases.store',
                expect.objectContaining({
                    phase_details: [expect.objectContaining({ form_access_control_id: 41 })],
                })
            )
        })

        it('[W94] tanpa Form aktif beraturan akses menampilkan banner dan menonaktifkan Simpan Tahap', () => {
            const wrapper = mountPage(creating([]))

            expect(wrapper.text()).toContain(
                'Belum ada Form aktif yang punya aturan akses. Buat aturan akses di Form Builder lebih dulu.'
            )
            const banner = wrapper.findAll('a').find((a) => a.text() === 'Buka Form Builder')
            expect(banner?.attributes('href')).toBe('/admin.forms.index')
            expect(wrapper.findAll('li')).toHaveLength(0)
            expect(saveButton(wrapper)?.attributes('disabled')).toBeDefined()
        })
    })
})
