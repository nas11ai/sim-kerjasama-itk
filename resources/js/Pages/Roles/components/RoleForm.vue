<script lang="ts" setup>
import InputError from "@/Components/InputError.vue";
import Button from "@/Components/ui/button/Button.vue";
import Checkbox from "@/Components/ui/checkbox/Checkbox.vue";
import Input from "@/Components/ui/input/Input.vue";
import Label from "@/Components/ui/label/Label.vue";
import Table from "@/Components/ui/table/Table.vue";
import TableBody from "@/Components/ui/table/TableBody.vue";
import TableCell from "@/Components/ui/table/TableCell.vue";
import TableHead from "@/Components/ui/table/TableHead.vue";
import TableHeader from "@/Components/ui/table/TableHeader.vue";
import TableRow from "@/Components/ui/table/TableRow.vue";
import { Permission } from "@/types";
import { CreateRoleRequest } from "@/types/formRequests";
import { useForm } from "@inertiajs/vue3";
import {
    ColumnDef,
    FlexRender,
    getCoreRowModel,
    useVueTable,
} from "@tanstack/vue-table";
import { LoaderCircle } from "lucide-vue-next";
import { h } from "vue";

const props = defineProps<{
    permissions: Permission[];
    submitUrl: string;
    defaultRole?: {
        name: string;
        permissions: string[];
    };
    isEdit?: boolean;
}>();

const form = useForm<CreateRoleRequest>({
    name: props.defaultRole?.name || "",
    permissions: props.defaultRole?.permissions || [],
});

const columns: ColumnDef<Permission>[] = [
    {
        accessorKey: "name",
        header: "Permission List",
    },
    {
        header: "Select",
        cell: ({ row }) =>
            h(Checkbox, {
                modelValue: form.permissions!.includes(row.original.name),
                "onUpdate:modelValue": (value: boolean | "indeterminate") => {
                    if (value === "indeterminate") return;
                    const permName = row.original.name;
                    if (value && !form.permissions!.includes(permName)) {
                        form.permissions!.push(permName);
                    } else if (!value) {
                        form.permissions = form.permissions!.filter(
                            (p) => p !== permName
                        );
                    }
                },
            }),
    },
];

const table = useVueTable({
    data: props.permissions,
    columns,
    getCoreRowModel: getCoreRowModel(),
});

const submit = () => {
    if (props.isEdit) {
        form.put(props.submitUrl, {
            preserveScroll: true,
        });
    } else {
        form.post(props.submitUrl, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
  <form
    class="flex flex-col gap-6"
    @submit.prevent="submit"
  >
    <div class="grid gap-6">
      <div class="grid gap-2">
        <Label for="name">Nama Role</Label>
        <Input
          id="name"
          v-model="form.name"
          placeholder="contoh.. Editor"
        />
        <InputError :message="form.errors.name" />
      </div>

      <div class="grid gap-2">
        <Label>Hak Akses</Label>
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
                  class="text-bold bg-muted border-b"
                >
                  {{ header.column.columnDef.header }}
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <template
                v-if="table.getRowModel().rows.length > 0"
              >
                <TableRow
                  v-for="row in table.getRowModel().rows"
                  :key="row.id"
                  class="h-8"
                >
                  <template
                    v-for="cell in row.getVisibleCells()"
                    :key="cell.id"
                  >
                    <TableCell>
                      <FlexRender
                        :render="
                          cell.column.columnDef.cell
                        "
                        :props="{
                          ...cell.getContext(),
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
                    Belum ada hak akses tersedia.
                  </TableCell>
                </TableRow>
              </template>
            </TableBody>
          </Table>
          <InputError :message="form.errors.permissions" />
        </div>
      </div>

      <Button
        type="submit"
        class="mt-4 w-full cursor-pointer"
        :disabled="form.processing"
      >
        <LoaderCircle
          v-if="form.processing"
          class="mr-2 h-4 w-4 animate-spin"
        />
        {{ isEdit ? "Perbarui Role" : "Buat Role" }}
      </Button>
    </div>
  </form>
</template>
