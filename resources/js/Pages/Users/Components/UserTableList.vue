<script lang="ts" setup>
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import TableCell from "@/Components/ui/table/TableCell.vue";
import { Auth, Role, User } from "@/types";
import { router, usePage } from "@inertiajs/vue3";
import {
    ColumnDef,
    FlexRender,
    getCoreRowModel,
    getPaginationRowModel,
    useVueTable,
} from "@tanstack/vue-table";
import { computed, h, ref } from "vue";
import DataTablePagination from "./DataTablePagination.vue";
import TableActionColumn from "./TableActionColumn.vue";
import DeleteDialog from "@/Components/DeleteDialog.vue";

const props = defineProps<{
    users: User[];
}>();

const pageProps = usePage().props;
const authId = (pageProps.auth as Auth).user.id;

const columns: ColumnDef<User>[] = [
    {
        accessorKey: "name",
        header: "Nama",
    },
    {
        accessorKey: "email",
        header: "Email",
    },
    {
        accessorKey: "roles",
        header: "Role",
        cell: ({ row }) => {
            const roles = row.getValue("roles") as Role[];
            return roles.length > 0
                ? roles.map((item) => item.name).join(", ")
                : "No Role";
        },
    },
    {
        accessorKey: "created_at",
        header: "Dibuat Pada",
        cell: ({ row }) => {
            const date = new Date(row.getValue("created_at"));
            return date.toLocaleDateString("en-US", {
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
            });
        },
    },
    {
        header: "Aksi",
        cell: ({ row }) => {
            const user = row.original;
            const isSuperAdmin = user.roles.some(
                (r) => r.name === "Super Admin"
            );
            console.log('Row:', user.name, 'isSuperAdmin?', isSuperAdmin);

            return h(TableActionColumn, {
                row,
                onConfirmDelete: (userId: number) => openConfirmDialog(userId),
                canEdit: !isSuperAdmin,
                canDelete: !isSuperAdmin && user.id !== authId,
            });
        },
    },
];

const pagination = ref({
    pageIndex: 0,
    pageSize: 15,
});

const table = computed(() =>
    useVueTable({
        data: props.users,
        columns,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        state: {
            pagination: pagination.value,
        },
        onPaginationChange: (updater) => {
            if (typeof updater === "function") {
                pagination.value = updater(pagination.value);
            } else {
                pagination.value = updater;
            }
        },
    })
);

const showDialog = ref(false);
const selectedUserId = ref<number | null>(null);

function openConfirmDialog(userId: number) {
    selectedUserId.value = userId;
    showDialog.value = true;
}

function confirmDelete() {
    if (selectedUserId.value !== null) {
        router.delete(
            route("admin.users.delete", { id: selectedUserId.value }),
            {
                preserveState: false,
            }
        );
        showDialog.value = false;
    }
}
</script>

<template>
    <div class="rounded-md border">
        <Table>
            <TableHeader>
                <TableRow
                    v-for="headerGroup in table.getHeaderGroups()"
                    :key="headerGroup.id"
                >
                    <TableHead
                        v-for="header in headerGroup.headers"
                        :key="header.id"
                        class="text-bold bg-muted"
                    >
                        {{ header.column.columnDef.header }}
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <template v-if="table.getRowModel().rows.length > 0">
                    <TableRow
                        v-for="row in table.getRowModel().rows"
                        :key="row.id"
                        class="h-12"
                    >
                        <template
                            v-for="cell in row.getVisibleCells()"
                            :key="cell.id"
                        >
                            <TableCell>
                                <FlexRender
                                    :render="cell.column.columnDef.cell"
                                    :props="{
                                        ...cell.getContext(),
                                        onConfirmDelete: openConfirmDialog,
                                    }"
                                />
                            </TableCell>
                        </template>
                    </TableRow>
                </template>
                <template v-else>
                    <TableRow>
                        <TableCell
                            :colspan="columns.length"
                            class="text-center"
                        >
                            No data available.
                        </TableCell>
                    </TableRow>
                </template>
            </TableBody>
        </Table>
    </div>

    <DataTablePagination :table="table" />
    <DeleteDialog v-model="showDialog" :confirmDelete="confirmDelete" />
</template>
