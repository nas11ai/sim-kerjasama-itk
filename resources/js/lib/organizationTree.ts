import type { OrganizationNodeData } from '@/types/system-config'

/**
 * Node yang tampil saat sebagian cabang ditutup. `nodes` berurutan depth-first, jadi keturunan
 * sebuah node adalah node sesudahnya yang `depth`-nya lebih dalam.
 */
export function visibleNodes(
    nodes: OrganizationNodeData[],
    collapsed: Set<number>
): OrganizationNodeData[] {
    const visible: OrganizationNodeData[] = []
    let hiddenBelow: number | null = null

    for (const node of nodes) {
        if (hiddenBelow !== null && node.depth > hiddenBelow) {
            continue
        }

        hiddenBelow = collapsed.has(node.id) ? node.depth : null
        visible.push(node)
    }

    return visible
}
