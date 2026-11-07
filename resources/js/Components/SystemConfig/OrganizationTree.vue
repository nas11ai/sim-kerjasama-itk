<script setup lang="ts">
import { ChevronDown, ChevronRight } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import { Badge } from '@/Components/ui/badge'
import { visibleNodes } from '@/lib/organizationTree'
import type { OrganizationNodeData } from '@/types/system-config'

const props = defineProps<{ nodes: OrganizationNodeData[]; selectedId: number | null }>()
const emit = defineEmits<{ select: [id: number] }>()

const collapsed = ref(new Set<number>())
const shown = computed(() => visibleNodes(props.nodes, collapsed.value))

function toggle(id: number) {
    const next = new Set(collapsed.value)
    if (!next.delete(id)) {
        next.add(id)
    }
    collapsed.value = next
}
</script>

<template>
    <ul role="tree" aria-label="Struktur Organisasi" class="flex flex-col gap-0.5 text-sm">
        <li
            v-for="node in shown"
            :key="node.id"
            role="treeitem"
            :aria-level="node.depth + 1"
            :aria-selected="node.id === selectedId ? 'true' : 'false'"
            :aria-expanded="node.hasChildren ? !collapsed.has(node.id) : undefined"
            class="flex items-center gap-1 rounded-md pr-2"
            :class="node.id === selectedId ? 'bg-muted font-medium' : 'hover:bg-muted/50'"
            :style="{ paddingLeft: `${node.depth * 1.25}rem` }"
        >
            <button
                v-if="node.hasChildren"
                type="button"
                class="rounded p-1 text-muted-foreground hover:text-foreground"
                :aria-expanded="!collapsed.has(node.id)"
                :aria-label="`${collapsed.has(node.id) ? 'Buka' : 'Tutup'} cabang ${node.name}`"
                @click="toggle(node.id)"
            >
                <ChevronRight v-if="collapsed.has(node.id)" class="size-4" />
                <ChevronDown v-else class="size-4" />
            </button>
            <span v-else class="w-6 shrink-0" aria-hidden="true" />
            <button
                type="button"
                data-select
                class="flex min-w-0 flex-1 items-center gap-2 py-1.5 text-left"
                @click="emit('select', node.id)"
            >
                <span class="truncate">{{ node.name }}</span>
                <span class="text-xs text-muted-foreground">{{ node.typeLabel }}</span>
                <Badge v-if="!node.isActive" variant="outline">Nonaktif</Badge>
            </button>
        </li>
    </ul>
</template>
