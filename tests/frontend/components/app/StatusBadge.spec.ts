import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import StatusBadge from '@/Components/app/StatusBadge.vue'
import { Badge } from '@/Components/ui/badge'

describe('StatusBadge', () => {
    it('menampilkan label dan varian dari backend', () => {
        const wrapper = mount(StatusBadge, {
            props: { status: { key: 'rejected', label: 'Ditolak', variant: 'destructive' } },
        })

        expect(wrapper.text()).toBe('Ditolak')
        expect(wrapper.findComponent(Badge).props('variant')).toBe('destructive')
    })
})
