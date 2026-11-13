import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { submitted, forms, router } = vi.hoisted(() => ({
    submitted: vi.fn<(method: string, url: string, data: unknown) => void>(),
    forms: [] as { errors: Record<string, string> }[],
    router: { post: vi.fn(), delete: vi.fn() },
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        router,
        useForm: <T extends object>(data: T) => {
            let transformer = (value: T): unknown => value
            const form = reactive({
                ...data,
                errors: {},
                processing: false,
                isDirty: false,
                defaults() {},
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
import type { PhaseDetailEditData, PhaseEditData, PhaseEditPageProps } from '@/types/form-engine'

const detail = (overrides: Partial<PhaseDetailEditData> = {}): PhaseDetailEditData => ({
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
    evaluationForms: [],
    ...overrides,
})

const phase = (
    steps: PhaseDetailEditData[],
    overrides: Partial<PhaseEditData> = {}
): PhaseEditData => ({
    id: 3,
    title: 'Tahap Proposal',
    description: null,
    isActive: true,
    steps,
    hasDraft: false,
    versionLabel: 'Versi 1',
    versions: [
        {
            label: 'Versi 1',
            publishedAt: '2026-11-01T02:00:00.000Z',
            publishedBy: 'Admin',
            cohortCount: 2,
        },
    ],
    impact: { nextLabel: 'Versi 2', pinnedCohorts: 0 },
    ...overrides,
})

const props = (
    steps: PhaseDetailEditData[],
    overrides: Partial<PhaseEditData> = {}
): PhaseEditPageProps => ({
    phase: phase(steps, overrides),
    accessOptions: [
        {
            value: '41',
            label: 'Form Proposal · Pengaju · Fakultas Sains',
            formId: 7,
            formTitle: 'Form Proposal',
        },
    ],
    phaseTypeOptions: [{ value: '5', label: 'Isi Proposal' }],
    evaluationOptions: [{ value: '3', label: 'Lembar Substansi', versionLabel: 'Versi 2' }],
    returnForm: null,
})

const route = (name: string, params?: number | Record<string, number>) => {
    if (params === undefined) return `/${name}`
    const ids = typeof params === 'number' ? [params] : Object.values(params)
    return `/${name}/${ids.join('/')}`
}

const mountPage = (pageProps: PhaseEditPageProps) =>
    mount(PhaseEditPage, {
        props: pageProps,
        global: { mocks: { route } },
        attachTo: document.body,
    })

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

const button = (label: string) => {
    const found = Array.from(document.querySelectorAll('button')).find(
        (b) => b.textContent?.trim() === label
    )
    if (found === undefined) throw new Error(`tombol "${label}" tidak ada`)
    return found
}

const tab = async (label: string) => {
    const trigger = Array.from(document.querySelectorAll('[role="tab"]')).find(
        (t) => t.textContent?.trim() === label
    )
    if (!(trigger instanceof HTMLElement)) throw new Error(`tab "${label}" tidak ada`)
    trigger.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))
    trigger.focus()
    await flushPromises()
}

let mounted: ReturnType<typeof mountPage> | null = null
const page = (pageProps: PhaseEditPageProps) => (mounted = mountPage(pageProps))

beforeEach(() => {
    vi.stubGlobal('route', route)
    submitted.mockClear()
    router.post.mockClear()
    forms.length = 0
})

afterEach(() => {
    mounted?.unmount()
    mounted = null
    document.body.innerHTML = ''
})

describe('FormEngine/PhaseEditPage', () => {
    it('judul, lencana versi, dan back ke pustaka Fase', () => {
        const wrapper = page(props([detail()]))

        expect(wrapper.find('h1').text()).toBe('Edit Phase: Tahap Proposal')
        expect(wrapper.text()).toContain('Versi 1')
        expect(wrapper.find('a[href="/admin.phases.index"]').text()).toContain('Fase')
        expect(wrapper.findAll('li')[0].text()).toContain('Isi Proposal · Form Proposal')
        expect(wrapper.findAll('li')[0].text()).toContain('Akses: Pengaju · Fakultas Sains')
        expect(wrapper.findAll('li')[0].text()).toContain('Jenis: Pengajuan')
    })

    it('banner draf menyebut versi yang masih dipakai angkatan baru', () => {
        const wrapper = page(props([detail()], { hasDraft: true }))

        expect(wrapper.text()).toContain(
            'Draf belum diterbitkan. Angkatan baru masih memakai Versi 1.'
        )
    })

    it('banner draf tahap yang belum pernah terbit', () => {
        const wrapper = page(
            props([detail()], { hasDraft: true, versionLabel: null, versions: [] })
        )

        expect(wrapper.text()).toContain('Belum terbit')
        expect(wrapper.text()).toContain(
            'Draf belum diterbitkan. Tahap ini belum bisa dipakai periode.'
        )
    })

    it('tanpa draf tidak ada banner dan Terbitkan nonaktif', () => {
        const wrapper = page(props([detail()]))

        expect(wrapper.text()).not.toContain('Draf belum diterbitkan')
        expect(button('Terbitkan').disabled).toBe(true)
    })

    it('langkah yang direview memuat bagian pemasangan Formulir Evaluasi (BR-FE-30)', () => {
        const wrapper = page(props([detail({ needsReview: true })]))

        expect(wrapper.text()).toContain('Form evaluasi untuk Isi Proposal · Form Proposal')
        expect(wrapper.text()).toContain('Pasang Formulir Evaluasi')
    })

    it('Simpan draf mengirim langkah beserta pemasangannya ke route draf', () => {
        page(
            props([
                detail({
                    needsReview: true,
                    evaluationForms: [
                        {
                            formId: 3,
                            title: 'Lembar Substansi',
                            versionLabel: 'Versi 2',
                            isRequired: false,
                            isActive: true,
                        },
                    ],
                }),
            ])
        )

        button('Simpan draf').click()

        expect(submitted).toHaveBeenCalledWith('put', '/admin.phases.draft.update/3', {
            phase_details: [
                {
                    form_access_control_id: 41,
                    phase_type_id: 5,
                    needs_review: true,
                    deadline_type: 'submit',
                    deadline_cycle: null,
                    evaluation_forms: [
                        { review_evaluation_form_id: 3, is_required: false, is_active: true },
                    ],
                },
            ],
        })
    })

    it('Simpan Tahap hanya mengirim judul, deskripsi, dan status', () => {
        page(props([detail()]))

        button('Simpan Tahap').click()

        expect(submitted).toHaveBeenCalledWith('put', '/admin.phases.update/3', {
            title: 'Tahap Proposal',
            description: '',
            is_active: true,
        })
    })

    it('konfirmasi Terbitkan tanpa angkatan terpaku hanya satu kalimat', async () => {
        page(props([detail()], { hasDraft: true }))

        button('Terbitkan').click()
        await flushPromises()

        const dialog = document.querySelector('[role="dialog"]')?.textContent ?? ''
        expect(dialog).toContain(
            'Terbitkan Versi 2? Berlaku untuk angkatan yang belum punya pengajuan.'
        )
        expect(dialog).not.toContain('versi lama')
    })

    it('konfirmasi Terbitkan menyebut angkatan yang tetap memakai versi lama', async () => {
        page(
            props([detail()], {
                hasDraft: true,
                impact: { nextLabel: 'Versi 2', pinnedCohorts: 2 },
            })
        )

        button('Terbitkan').click()
        await flushPromises()

        expect(document.querySelector('[role="dialog"]')?.textContent).toContain(
            'Terbitkan Versi 2? Berlaku untuk angkatan yang belum punya pengajuan. 2 angkatan sudah memakai versi lama dan tetap memakainya.'
        )
        button('Terbitkan Versi 2').click()
        expect(router.post).toHaveBeenCalledWith('/admin.phases.publish/3', {}, expect.any(Object))
    })

    it('Pratinjau merender langkah draf baca-saja dengan nama tenggatnya (G3-W8)', async () => {
        page(
            props([
                detail({
                    needsReview: true,
                    evaluationForms: [
                        {
                            formId: 3,
                            title: 'Lembar Substansi',
                            versionLabel: 'Versi 2',
                            isRequired: true,
                            isActive: true,
                        },
                    ],
                }),
                detail({ order: 2, deadlineCycle: 1, deadlineLabel: 'Laporan Monev I' }),
            ])
        )

        await tab('Pratinjau')

        const preview = document.querySelector('[data-preview]')
        expect(preview?.textContent).toContain('Langkah 1')
        expect(preview?.textContent).toContain('Laporan Monev I')
        expect(preview?.textContent).toContain('Lembar Substansi')
        expect(preview?.querySelector('button')).toBeNull()
    })

    it('Versi & pemakaian mendaftar versi dan angkatan pemakainya', async () => {
        page(props([detail()]))

        await tab('Versi & pemakaian')

        expect(document.body.textContent).toContain('dipakai 2 angkatan')
        expect(document.body.textContent).toContain('oleh Admin')
    })

    it('[FE-WINDOW-09] memilih Upload Luaran mematikan Perlu Review', async () => {
        const wrapper = page(props([detail({ needsReview: true })]))

        await openRow(wrapper, 0)
        await chooseStepKind(wrapper, 'output:')

        const toggle = Array.from(
            document.body.querySelectorAll('[role="dialog"] [role="switch"]')
        )[0]
        expect(toggle?.hasAttribute('disabled')).toBe(true)
        expect(toggle?.getAttribute('aria-checked')).toBe('false')
        expect(document.body.textContent).toContain('Detail Upload Luaran tidak direview.')
    })

    it('galat jenis langkah tampil di baris langkahnya', async () => {
        const wrapper = page(props([detail(), detail({ order: 2, deadlineCycle: 1 })]))

        forms[forms.length - 1].errors = {
            'phase_details.1.deadline_cycle': 'Upload Luaran tidak bersiklus.',
        }
        await nextTick()

        const rows = wrapper.findAll('ul.divide-y > li')
        expect(rows[1].text()).toContain('Upload Luaran tidak bersiklus.')
        expect(rows[0].text()).not.toContain('tidak bersiklus')
    })

    it('[B5] dari pustaka bertapis: Kembali membawa Form tapisnya', () => {
        const wrapper = page({ ...props([detail()]), returnForm: 7 })

        expect(wrapper.find('a[href="/admin.phases.index/7"]').text()).toContain('Fase')
    })

    describe('mode buat', () => {
        const creating = (accessOptions = props([]).accessOptions): PhaseEditPageProps => ({
            ...props([]),
            phase: phase([], {
                id: null,
                title: '',
                versionLabel: null,
                versions: [],
                impact: { nextLabel: 'Versi 1', pinnedCohorts: 0 },
            }),
            accessOptions,
        })

        it('judul Buat Tahap Baru dan satu langkah Pengajuan kosong berakses pilihan pertama', () => {
            const wrapper = page(creating())

            expect(wrapper.find('h1').text()).toBe('Buat Tahap Baru')
            expect(wrapper.findAll('ul.divide-y > li')).toHaveLength(1)
            expect(wrapper.text()).toContain('Isi Proposal · Form Proposal')
            expect(wrapper.text()).toContain('Baru')
            expect(wrapper.text()).not.toContain('Simpan draf')
        })

        it('Buat Tahap mengirim post ke admin.phases.store dengan Form tapisnya', async () => {
            const wrapper = page({ ...creating(), returnForm: 7 })

            expect(
                wrapper
                    .findAll('a')
                    .find((a) => a.text() === 'Batalkan')
                    ?.attributes('href')
            ).toBe('/admin.phases.index/7')
            await wrapper.find('form').trigger('submit')

            expect(submitted).toHaveBeenCalledWith(
                'post',
                '/admin.phases.store',
                expect.objectContaining({
                    form: 7,
                    title: '',
                    phase_details: [
                        expect.objectContaining({
                            form_access_control_id: 41,
                            evaluation_forms: [],
                        }),
                    ],
                })
            )
        })

        it('[W94] tanpa Form aktif beraturan akses menampilkan banner dan menonaktifkan Buat Tahap', () => {
            const wrapper = page(creating([]))

            expect(wrapper.text()).toContain(
                'Belum ada Form aktif yang punya aturan akses. Buat aturan akses di Pembuat Formulir lebih dulu.'
            )
            const banner = wrapper.findAll('a').find((a) => a.text() === 'Buka Pembuat Formulir')
            expect(banner?.attributes('href')).toBe('/admin.forms.index')
            expect(button('Buat Tahap').disabled).toBe(true)
        })
    })
})
