<!-- resources/js/Pages/Roles/Index.vue -->
<script setup lang="ts">
import DataTable from "@/Components/DataTable.vue";
import { Button } from "@/Components/ui/button";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Role, User } from "@/types";
import { Head, Link } from "@inertiajs/vue3";
import { ColumnDef } from "@tanstack/vue-table";
import { Plus } from "lucide-vue-next";
import { h, ref } from "vue";
import PermissionChip from "./components/PermissionChip.vue";
import TableActionColumn from "@/Components/TableActionColumn.vue";

const props = defineProps<{
    roles: Role[];
    can: {
        create: boolean;
        delete: boolean;
        edit: boolean;
    };
}>();

const dataTableRef = ref<InstanceType<typeof DataTable>>();

const columns: ColumnDef<Role>[] = [
    {
        accessorKey: "name",
        header: "Role",
    },
    {
        header: "Hak Akses",
        cell: ({ row }) =>
            h("div", { class: "max-w-[540px]" }, [
                h(PermissionChip, {
                    permissions: row.original.permissions,
                }),
            ]),
    },
    {
        accessorKey: "actions",
        header: "Aksi",
        cell: ({ row }) => {
            const role = row.original;
            const isSuperAdmin = role.name === "Super Admin"; // atau RoleEnum.SUPER_ADMIN

            return h(TableActionColumn, {
                row,
                canDelete: props.can.delete && !isSuperAdmin,
                canEdit: props.can.edit && !isSuperAdmin,
                editRouteName: "admin.roles.edit",
                onConfirmDelete: (id: number) =>
                    dataTableRef.value?.openConfirmDialog(id),
            });
        },
    },
];
</script>

<template>
    <Head title="Manajemen Role" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Manajemen Role
                </h2>
                <Link :href="route('admin.roles.create')">
                    <Button>
                        <Plus class="h-4 w-4 mr-2" />
                        Buat Role
                    </Button>
                </Link>
            </div>
        </template>
        <div class="space-y-6">
            <DataTable
                ref="dataTableRef"
                :data="props.roles"
                :columns="columns"
                deleteRouteName="admin.roles.destroy"
                :cellAligns="{
                    name: 'align-top',
                    permissions: 'align-top',
                    actions: 'align-top',
                }"
            >
                <template #delete-dialog-content>
                    <p class="text-muted-foreground text-sm">
                        Apakah Anda yakin ingin menghapus <strong>role</strong> ini?                        
                        Tindakan ini tidak dapat dibatalkan. Ini akan menghapus data secara permanen.
                    </p>
                </template>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
