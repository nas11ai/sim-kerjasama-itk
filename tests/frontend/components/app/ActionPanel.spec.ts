import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))

import ActionPanel from '@/Components/app/ActionPanel.vue'
import type { ActionItem } from '@/types/action'

const edit: ActionItem = {
    key: 'edit',
    label: 'Ubah Pengajuan',
    href: '/edit',
    available: true,
    hint: 'Tersedia saat pengajuan masih berstatus Draf.',
}

const mountPanel = (actions: ActionItem[]) => mount(ActionPanel, { props: { actions } })

describe('ActionPanel', () => {
    it('menautkan aksi tersedia ke href', () => {
        const link = mountPanel([edit]).find('a')

        expect(link.attributes('href')).toBe('/edit')
        expect(link.text()).toContain('Ubah Pengajuan')
    })

    it('memanggil onSelect', async () => {
        const onSelect = vi.fn()
        const wrapper = mountPanel([{ ...edit, href: undefined, onSelect }])

        await wrapper.find('button').trigger('click')

        expect(onSelect).toHaveBeenCalledTimes(1)
    })

    it('menonaktifkan aksi tidak tersedia tanpa href aktif', () => {
        const wrapper = mountPanel([{ ...edit, available: false }])

        expect(wrapper.find('a').exists()).toBe(false)
        expect(wrapper.find('button').attributes('disabled')).toBeDefined()
    })

    it('selalu menampilkan hint', () => {
        const wrapper = mountPanel([edit, { ...edit, key: 'other', available: false }])

        expect(
            wrapper.text().match(/Tersedia saat pengajuan masih berstatus Draf\./g)
        ).toHaveLength(2)
    })
})
