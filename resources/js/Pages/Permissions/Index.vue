<!-- resources/js/Pages/Permission/Index.vue -->
<script setup lang="ts">
import DataTable from "@/Components/DataTable.vue";
import TableActionColumn from "@/Components/TableActionColumn.vue";
import Button from "@/Components/ui/button/Button.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Permission } from "@/types";
import { Head, Link } from "@inertiajs/vue3";
import { ColumnDef } from "@tanstack/vue-table";
import { Plus } from "lucide-vue-next";
import { h, ref } from "vue";
import CreateDialogue from "./components/CreateDialogue.vue";
import EditDialogue from "./components/EditDialogue.vue";

const props = defineProps<{
    permissions: Permission[];
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
}>();

const dataTableRef = ref<InstanceType<typeof DataTable>>();
const showCreateModal = ref(false);
const showEditModal = ref(false);
const selectedPermission = ref<Permission | null>(null);

const columns: ColumnDef<Permission>[] = [
    {
        accessorKey: "name",
        header: "Hak Akses",
    },
    {
        header: "Aksi",
        cell: ({ row }) =>
            h(TableActionColumn, {
                row,
                canDelete: props.can.delete,
                canEdit: props.can.update,
                onEdit: (permission: Permission) => {
                    selectedPermission.value = permission;
                    showEditModal.value = true;
                },
                onConfirmDelete: (id: number) =>
                    dataTableRef.value?.openConfirmDialog(id),
            }),
    },
];
</script>

<template>
  <Head title="Manajemen Hak Akses" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
          Manajemen Hak Akses
        </h2>
        <Button
          :disabled="!props.can.create"
          @click="showCreateModal = true"
        >
          <Plus class="h-4 w-4 mr-2" />
          Buat Hak Akses
        </Button>
        <CreateDialogue
          :open="showCreateModal"
          @close="showCreateModal = false"
        />
      </div>
    </template>

    <div class="space-y-6">
      <DataTable
        ref="dataTableRef"
        :data="props.permissions"
        :columns="columns"
        delete-route-name="admin.permissions.destroy"
      >
        <template #delete-dialog-content>
          <p class="text-muted-foreground text-sm">
            Apakah Anda yakin ingin menghapus <strong>hak akses</strong> ini?
            Tindakan ini tidak dapat dibatalkan. Ini akan menghapus data secara permanen.
          </p>
        </template>
      </DataTable>
      <EditDialogue
        v-if="selectedPermission"
        :permission="selectedPermission"
        :open="showEditModal"
        @close="showEditModal = false"
      />
    </div>
  </AuthenticatedLayout>
</template>
