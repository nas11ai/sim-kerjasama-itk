import { describe, expect, it } from 'vitest'

import { visibleNodes } from '@/lib/organizationTree'
import type { OrganizationNodeData } from '@/types/system-config'

const node = (id: number, depth: number, hasChildren = false): OrganizationNodeData => ({
    id,
    name: `Unit ${id}`,
    typeLabel: 'Unit Kerja',
    isActive: true,
    parentId: null,
    depth,
    hasChildren,
})

// ITK(1) → FTI(2) → Informatika(3) → Lab(4); ITK → Sains(5); Akademi(6)
const tree = [
    node(1, 0, true),
    node(2, 1, true),
    node(3, 2, true),
    node(4, 3),
    node(5, 1),
    node(6, 0),
]
const ids = (nodes: OrganizationNodeData[]) => nodes.map((n) => n.id)

describe('visibleNodes', () => {
    it('tanpa cabang tertutup semua node tampil', () => {
        expect(ids(visibleNodes(tree, new Set()))).toEqual([1, 2, 3, 4, 5, 6])
    })

    it('menutup cabang menyembunyikan seluruh keturunan, bukan hanya anak langsung', () => {
        expect(ids(visibleNodes(tree, new Set([2])))).toEqual([1, 2, 5, 6])
    })

    it('menutup akar menyembunyikan seluruh pohonnya sampai akar berikutnya', () => {
        expect(ids(visibleNodes(tree, new Set([1])))).toEqual([1, 6])
    })

    it('cabang tertutup di dalam cabang tertutup tidak membuka apa pun', () => {
        expect(ids(visibleNodes(tree, new Set([1, 3])))).toEqual([1, 6])
    })
})
