import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))

import PageHeader from '@/Components/app/PageHeader.vue'

describe('PageHeader', () => {
    // E2E-01 — dua tombol aksi berdampingan melebihi 390px bila tidak boleh turun baris.
    it('tombol aksi boleh turun baris di layar sempit', () => {
        const wrapper = mount(PageHeader, {
            props: { title: 'Daftar User' },
            slots: {
                actions: '<button>Undangan Registrasi</button><button>Antrian Verifikasi</button>',
            },
        })

        expect(wrapper.get('button').element.parentElement?.classList).toContain('flex-wrap')
    })
})
