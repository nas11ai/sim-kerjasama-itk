<script setup lang="ts">
import { type Table } from '@tanstack/vue-table'
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-vue-next'
import Select from './ui/select/Select.vue'
import SelectTrigger from './ui/select/SelectTrigger.vue'
import SelectValue from './ui/select/SelectValue.vue'
import SelectContent from './ui/select/SelectContent.vue'
import SelectItem from './ui/select/SelectItem.vue'
import Button from './ui/button/Button.vue'

interface DataTablePaginationProps<T> {
    table: Table<T>
    pageSizes?: number[]
    label?: string
}

defineProps<DataTablePaginationProps<any>>()
</script>

<template>
    <div class="flex items-center justify-center sm:justify-end">
        <div class="flex flex-col items-center sm:flex-row lg:space-x-8">
            <div class="flex items-center space-x-2">
                <p class="text-sm font-medium">
                    {{ label || 'Baris per halaman' }}
                </p>
                <Select
                    :model-value="`${table.getState().pagination.pageSize}`"
                    @update:model-value="(value) => table.setPageSize(Number(value))"
                >
                    <SelectTrigger class="h-8 w-[70px]">
                        <SelectValue :placeholder="`${table.getState().pagination.pageSize}`" />
                    </SelectTrigger>
                    <SelectContent side="top">
                        <SelectItem
                            v-for="pageSize in pageSizes || [10, 15, 20, 25, 50, 100]"
                            :key="pageSize"
                            :value="`${pageSize}`"
                        >
                            {{ pageSize }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="mt-2 flex flex-row items-center space-x-2 sm:mt-0 sm:space-x-4">
                <div class="flex w-[120px] items-center justify-center text-sm font-medium">
                    Halaman {{ table.getState().pagination.pageIndex + 1 }} dari
                    {{ table.getPageCount() }}
                </div>

                <div class="flex items-center space-x-2">
                    <Button
                        variant="outline"
                        class="hidden h-8 w-8 p-0 lg:flex"
                        :disabled="!table.getCanPreviousPage()"
                        @click="table.setPageIndex(0)"
                    >
                        <span class="sr-only">Pergi ke halaman pertama</span>
                        <ChevronsLeft class="h-4 w-4" />
                    </Button>

                    <Button
                        variant="outline"
                        class="h-8 w-8 p-0"
                        :disabled="!table.getCanPreviousPage()"
                        @click="table.previousPage()"
                    >
                        <span class="sr-only">Pergi ke halaman sebelumnya</span>
                        <ChevronLeft class="h-4 w-4" />
                    </Button>

                    <Button
                        variant="outline"
                        class="h-8 w-8 p-0"
                        :disabled="!table.getCanNextPage()"
                        @click="table.nextPage()"
                    >
                        <span class="sr-only">Pergi ke halaman berikutnya</span>
                        <ChevronRight class="h-4 w-4" />
                    </Button>

                    <Button
                        variant="outline"
                        class="hidden h-8 w-8 p-0 lg:flex"
                        :disabled="!table.getCanNextPage()"
                        @click="table.setPageIndex(table.getPageCount() - 1)"
                    >
                        <span class="sr-only">Pergi ke halaman terakhir</span>
                        <ChevronsRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
