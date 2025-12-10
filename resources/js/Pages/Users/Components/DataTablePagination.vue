<script setup lang="ts">
import { Button } from "@/Components/ui/button";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import { User } from "@/types";
import { type Table } from "@tanstack/vue-table";
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from "lucide-vue-next";

interface DataTablePaginationProps {
    table: Table<User>;
}
defineProps<DataTablePaginationProps>();
</script>

<template>
    <div class="flex items-center justify-center sm:justify-end">
        <div class="flex flex-col items-center sm:flex-row lg:space-x-8">
            <div class="flex items-center space-x-2">
                <p class="text-sm font-medium">Baris per halaman</p>
                <Select
                    :model-value="`${table.getState().pagination.pageSize}`"
                    @update:model-value="
                        (value) => table.setPageSize(Number(value))
                    "
                >
                    <SelectTrigger class="h-8 w-[70px]">
                        <SelectValue
                            :placeholder="`${
                                table.getState().pagination.pageSize
                            }`"
                        />
                    </SelectTrigger>
                    <SelectContent side="top">
                        <SelectItem
                            v-for="pageSize in [10, 15, 20, 25, 50, 100]"
                            :key="pageSize"
                            :value="`${pageSize}`"
                        >
                            {{ pageSize }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div
                class="flex flex-row mt-2 sm:mt-0 items-center space-x-2 sm:space-x-4"
            >
                <div
                    class="flex w-[120px] items-center justify-center text-sm font-medium"
                >
                    Halaman {{ table.getState().pagination.pageIndex + 1 }} dari
                    {{ table.getPageCount() }}
                </div>
                <div class="flex items-center space-x-2">
                    <Button
                        variant="outline"
                        class="hidden h-8 w-8 cursor-pointer p-0 disabled:cursor-default lg:flex"
                        :disabled="!table.getCanPreviousPage()"
                        @click="table.setPageIndex(0)"
                    >
                        <span class="sr-only">Go to first page</span>
                        <ChevronsLeft class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        class="h-8 w-8 cursor-pointer p-0 disabled:cursor-default"
                        :disabled="!table.getCanPreviousPage()"
                        @click="table.previousPage()"
                    >
                        <span class="sr-only">Ke halaman sebelumnya</span>
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        class="h-8 w-8 cursor-pointer p-0 disabled:cursor-default"
                        :disabled="!table.getCanNextPage()"
                        @click="table.nextPage()"
                    >
                        <span class="sr-only">Ke halaman berikutnya</span>
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        class="hidden h-8 w-8 cursor-pointer p-0 disabled:cursor-default lg:flex"
                        :disabled="!table.getCanNextPage()"
                        @click="table.setPageIndex(table.getPageCount() - 1)"
                    >
                        <span class="sr-only">Ke halaman terakhir</span>
                        <ChevronsRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
