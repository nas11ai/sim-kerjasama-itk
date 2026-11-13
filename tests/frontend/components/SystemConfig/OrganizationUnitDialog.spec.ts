import { flushPromises, mount } from '@vue/test-utils'
import { nextTick, reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

type Payload = Record<string, unknown>

const { submitted, forms } = vi.hoisted(() => ({
    submitted: vi.fn(),
    forms: [] as { errors: Record<string, string> }[],
}))

vi.mock('@inertiajs/vue3', () => ({
    useForm: <T extends object>(data: T) => {
        let transform = (payload: Payload): Payload => payload
        const form = reactive({
            ...data,
            errors: {} as Record<string, string>,
            processing: false,
            clearErrors() {
                form.errors = {}
            },
            transform(fn: (payload: Payload) => Payload) {
                transform = fn
                return form
            },
            post(url: string) {
                submitted('post', url, transform(fields()))
            },
            put(url: string) {
                submitted('put', url, transform(fields()))
            },
        })
        const fields = (): Payload =>
            Object.fromEntries(Object.keys(data).map((key) => [key, Reflect.get(form, key)]))
        forms.push(form)
        return form
    },
}))

import OptionSelect from '@/Components/app/OptionSelect.vue'
import OrganizationUnitDialog from '@/Components/SystemConfig/OrganizationUnitDialog.vue'
import type { OrganizationUnitData } from '@/types/system-config'

const unit: OrganizationUnitData = {
    id: 2,
    name: 'FTI',
    type: 'faculty',
    typeLabel: 'Fakultas',
    isActive: true,
    parentId: 1,
    census: {
        directProfiles: 0,
        directActiveUsers: 0,
        subtreeProfiles: 0,
        subtreeActiveUsers: 0,
        unitsWithActiveUsers: 0,
        pendingInvitations: 0,
    },
    canDeactivate: true,
    canDelete: true,
    canReactivate: false,
    revivableInvitations: 0,
}

const route = (name: string, id?: number) => (id === undefined ? `/${name}` : `/${name}/${id}`)

const mountDialog = async (mode: 'root' | 'child' | 'edit') => {
    const wrapper = mount(OrganizationUnitDialog, {
        props: {
            mode,
            unit: mode === 'root' ? null : unit,
            open: true,
            typeOptions: [
                { value: 'faculty', label: 'Fakultas' },
                { value: 'unit', label: 'Unit Kerja' },
            ],
            parentOptions: [
                { value: '1', label: 'ITK, beserta unit di bawahnya' },
                { value: '2', label: 'FTI' },
                { value: '3', label: 'Informatika' },
            ],
        },
        global: { mocks: { route } },
        attachTo: document.body,
    })
    await flushPromises()
    return wrapper
}

const fill = async (name: string) => {
    const input = document.querySelector<HTMLInputElement>('#organization-unit-name')
    if (input === null) throw new Error('isian nama tidak ada')
    input.value = name
    input.dispatchEvent(new Event('input'))
    await nextTick()
}

const submit = async () => {
    document.querySelector<HTMLFormElement>('form')?.requestSubmit()
    await flushPromises()
}

beforeEach(() => {
    vi.stubGlobal('route', route)
    submitted.mockClear()
    forms.length = 0
    document.body.innerHTML = ''
})

describe('OrganizationUnitDialog', () => {
    it('[IAM-ORG-09] tambah unit induk mengirim tanpa parent_id', async () => {
        const wrapper = await mountDialog('root')
        await fill('Pusat Studi Energi')
        wrapper.findComponent(OptionSelect).vm.$emit('update:modelValue', 'unit')
        await submit()

        expect(document.body.textContent).toContain('Tambah Unit Induk')
        expect(submitted).toHaveBeenCalledWith('post', '/admin.organizations.store', {
            name: 'Pusat Studi Energi',
            type: 'unit',
            parent_id: null,
        })
    })

    it('[IAM-ORG-10] tambah sub-unit mengirim parent_id unit terpilih', async () => {
        const wrapper = await mountDialog('child')
        await fill('Teknik Mesin')
        wrapper.findComponent(OptionSelect).vm.$emit('update:modelValue', 'unit')
        await submit()

        expect(document.body.textContent).toContain('Tambah Sub-unit di FTI')
        expect(submitted).toHaveBeenCalledWith('post', '/admin.organizations.store', {
            name: 'Teknik Mesin',
            type: 'unit',
            parent_id: 2,
        })
    })

    it('menyunting mengirim nama, tipe, dan induk ke update', async () => {
        await mountDialog('edit')
        await fill('Fakultas Teknologi Industri')
        await submit()

        expect(document.body.textContent).toContain('Ubah Unit')
        expect(submitted).toHaveBeenCalledWith('put', '/admin.organizations.update/2', {
            name: 'Fakultas Teknologi Industri',
            type: 'faculty',
            parent_id: 1,
        })
    })

    it('[IAM-ORG-13] galat induk tampil di field', async () => {
        await mountDialog('edit')
        forms[0].errors = { parent_id: 'Unit ini tidak bisa dipindah ke bawah turunannya sendiri.' }
        await nextTick()

        expect(document.body.textContent).toContain(
            'Unit ini tidak bisa dipindah ke bawah turunannya sendiri.'
        )
    })

    it('[IAM-ORG-16] galat induk nonaktif tampil saat menambah sub-unit', async () => {
        await mountDialog('child')
        forms[0].errors = {
            parent_id: 'Unit induk sudah nonaktif, jadi tidak menerima sub-unit baru.',
        }
        await nextTick()

        expect(document.body.textContent).toContain('Unit induk sudah nonaktif')
    })

    it('pilihan induk tidak memuat unit itu sendiri', async () => {
        const selects = (await mountDialog('edit')).findAllComponents(OptionSelect)
        const parent = selects[selects.length - 1]

        expect(parent.props('options').map((o: { value: string }) => o.value)).not.toContain('2')
        expect(parent.props('options').map((o: { value: string }) => o.value)).toContain('3')
    })
})
