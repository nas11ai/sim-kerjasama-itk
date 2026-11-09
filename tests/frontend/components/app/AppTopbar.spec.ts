import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'

import AppTopbar from '@/Components/app/AppTopbar.vue'
import CommandPalette from '@/Components/app/CommandPalette.vue'
import { SidebarProvider } from '@/Components/ui/sidebar'

vi.mock('@/Components/app/CommandPalette.vue', () => ({
    default: defineComponent({
        name: 'CommandPalette',
        props: { open: Boolean },
        render: () => null,
    }),
}))

let wrapper: ReturnType<typeof mount>

function mountTopbar() {
    wrapper = mount(
        defineComponent({ render: () => h(SidebarProvider, null, () => h(AppTopbar)) }),
        { attachTo: document.body }
    )

    return wrapper
}

const palette = () => wrapper.findComponent(CommandPalette)

function tekan(init: KeyboardEventInit): KeyboardEvent {
    const event = new KeyboardEvent('keydown', { cancelable: true, ...init })
    window.dispatchEvent(event)

    return event
}

afterEach(() => {
    wrapper.unmount()
    vi.restoreAllMocks()
})

describe('AppTopbar', () => {
    it('tombol bertuliskan placeholder membuka palette', async () => {
        mountTopbar()
        const button = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Cari halaman, pengajuan, user...'))

        expect(palette().props('open')).toBe(false)
        await button?.trigger('click')

        expect(palette().props('open')).toBe(true)
    })

    it.each([{ ctrlKey: true }, { metaKey: true }])('%o + K membuka palette', async (modifier) => {
        mountTopbar()

        const event = tekan({ key: 'k', ...modifier })
        await wrapper.vm.$nextTick()

        expect(event.defaultPrevented).toBe(true)
        expect(palette().props('open')).toBe(true)
    })

    it('K tanpa modifier tidak membuka', async () => {
        mountTopbar()

        const event = tekan({ key: 'k' })
        await wrapper.vm.$nextTick()

        expect(event.defaultPrevented).toBe(false)
        expect(palette().props('open')).toBe(false)
    })

    it('label shortcut ⌘K di macOS, Ctrl K di platform lain', () => {
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0)'
        )
        expect(mountTopbar().find('kbd').text()).toBe('⌘K')
        wrapper.unmount()

        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue('Mozilla/5.0 (X11; Linux x86_64)')
        expect(mountTopbar().find('kbd').text()).toBe('Ctrl K')
    })
})
