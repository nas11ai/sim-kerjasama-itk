<script setup lang="ts" generic="T extends { id: number }">
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'

defineProps<{
    rows: T[]
    columns: { key: string; label: string }[]
}>()

defineSlots<{
    [key: `cell-${string}`]: (props: { row: T }) => unknown
    empty?: () => unknown
}>()
</script>

<template>
    <div class="rounded-md border">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead v-for="column in columns" :key="column.key">{{
                        column.label
                    }}</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <template v-if="rows.length > 0">
                    <TableRow v-for="row in rows" :key="row.id">
                        <TableCell v-for="column in columns" :key="column.key">
                            <slot :name="`cell-${column.key}`" :row="row" />
                        </TableCell>
                    </TableRow>
                </template>
                <TableEmpty v-else :colspan="columns.length">
                    <slot name="empty" />
                </TableEmpty>
            </TableBody>
        </Table>
    </div>
</template>
