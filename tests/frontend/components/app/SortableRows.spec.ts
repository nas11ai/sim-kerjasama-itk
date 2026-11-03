import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { defineComponent, ref } from 'vue'

import SortableRows from '@/Components/app/SortableRows.vue'

interface Row {
    key: string
    label: string
}

const Host = defineComponent({
    components: { SortableRows },
    setup() {
        const items = ref<Row[]>([
            { key: 'a', label: 'Satu' },
            { key: 'b', label: 'Dua' },
            { key: 'c', label: 'Tiga' },
        ])
        const labelOf = (item: Row) => item.label
        return { items, labelOf }
    },
    template: `<SortableRows v-model="items" :label-of="labelOf">
        <template #row="{ item, index }"><span class="row">{{ index }}:{{ item.label }}</span></template>
        <template #actions="{ item, index }"><button class="act">Aksi {{ item.key }}{{ index }}</button></template>
    </SortableRows>`,
})

const order = (wrapper: ReturnType<typeof mount>) =>
    wrapper.findAll('.row').map((row) => row.text())
const move = (wrapper: ReturnType<typeof mount>, label: string) =>
    wrapper.get(`button[aria-label="${label}"]`)

describe('SortableRows', () => {
    it('menyerahkan item dan index ke slot row dan actions', () => {
        const wrapper = mount(Host)

        expect(order(wrapper)).toEqual(['0:Satu', '1:Dua', '2:Tiga'])
        expect(wrapper.findAll('.act').map((b) => b.text())).toEqual([
            'Aksi a0',
            'Aksi b1',
            'Aksi c2',
        ])
    })

    it('Naik dan Turun memindahkan item', async () => {
        const wrapper = mount(Host)

        await move(wrapper, 'Turun: Satu').trigger('click')
        expect(order(wrapper)).toEqual(['0:Dua', '1:Satu', '2:Tiga'])

        await move(wrapper, 'Naik: Tiga').trigger('click')
        expect(order(wrapper)).toEqual(['0:Dua', '1:Tiga', '2:Satu'])
    })

    it('Naik disabled di baris pertama dan Turun di baris terakhir', () => {
        const wrapper = mount(Host)

        expect(move(wrapper, 'Naik: Satu').attributes('disabled')).toBeDefined()
        expect(move(wrapper, 'Turun: Tiga').attributes('disabled')).toBeDefined()
        expect(move(wrapper, 'Turun: Satu').attributes('disabled')).toBeUndefined()
        expect(move(wrapper, 'Naik: Tiga').attributes('disabled')).toBeUndefined()
    })
})
