<script lang="ts" setup>
import { Row } from '@tanstack/vue-table'
import { Edit, Trash } from 'lucide-vue-next'
import Button from './ui/button/UiButton.vue'
import { route } from 'ziggy-js'

interface TableRowData {
    id: number
}
const props = defineProps<{
    row: Row<TableRowData>
    canDelete: boolean
    canEdit: boolean
    editRouteName?: string
    onEdit?: (_row: TableRowData) => void
}>()

const emit = defineEmits<{
    'confirm-delete': [id: number]
}>()

function handleEdit() {
    if (props.onEdit) {
        props.onEdit(props.row.original)
    } else if (props.editRouteName) {
        window.location.href = route(props.editRouteName, {
            id: props.row.original.id,
        })
    }
}
</script>

<template>
    <div class="flex items-center gap-2">
        <Button
            type="button"
            variant="default"
            size="icon"
            class="cursor-pointer bg-yellow-400 hover:bg-yellow-400/80"
            :disabled="!props.canEdit"
            @click="handleEdit"
        >
            <Edit :size="16" class="text-black" />
        </Button>

        <Button
            as="button"
            variant="destructive"
            size="icon"
            class="cursor-pointer bg-red-500 hover:bg-red-500/80"
            :disabled="!props.canDelete"
            @click="emit('confirm-delete', props.row.original.id)"
        >
            <Trash :size="16" class="text-black" />
        </Button>
    </div>
</template>
