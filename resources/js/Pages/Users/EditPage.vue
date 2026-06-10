<script setup lang="ts">
import InputError from '@/Components/InputError.vue'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import Checkbox from '@/Components/ui/checkbox/UiCheckbox.vue'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { route } from 'ziggy-js'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Permission, Role, User } from '@/types'
import { UpdateUserRequest } from '@/types/formRequests'
import { Head, useForm } from '@inertiajs/vue3'
import { ColumnDef, FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { ArrowLeft, LoaderCircle } from 'lucide-vue-next'
import { h } from 'vue'

const props = defineProps<{
    roles: Role[]
    user: User
    permissions: Permission[]
}>()

const user = props.user as User

const form = useForm<UpdateUserRequest>({
    name: user.name,
    email: user.email,
    password: '',
    role: user.roles.length > 0 ? user.roles[0].name : '',
    permissions: user.permissions ? user.permissions.map((p: Permission) => p.name) : [],
})

const submit = () => {
    form.put(route('admin.users.update', user.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('password')
        },
    })
}

const columns: ColumnDef<Permission>[] = [
    {
        accessorKey: 'name',
        header: 'Permission List',
    },
    {
        header: 'Select',
        cell: ({ row }) =>
            h(Checkbox, {
                modelValue: form.permissions!.includes(row.original.name),
                'onUpdate:modelValue': (value: boolean | 'indeterminate') => {
                    if (value === 'indeterminate') return
                    const permName = row.original.name
                    if (value && !form.permissions!.includes(permName)) {
                        form.permissions!.push(permName)
                    } else if (!value) {
                        form.permissions = form.permissions!.filter((p) => p !== permName)
                    }
                },
            }),
    },
]

const table = useVueTable({
    data: props.permissions,
    columns,
    getCoreRowModel: getCoreRowModel(),
})
</script>

<template>
    <Head title="Edit User" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Button
                    variant="ghost"
                    class="p-0 mr-2"
                    size="sm"
                    @click="$inertia.visit(route('admin.users.index'))"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Kembali
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Edit User</h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Edit User</CardTitle>
                    <CardDescription> Isi detail untuk mengedit user. </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <form class="flex flex-col gap-6" @submit.prevent="submit">
                        <div class="grid grid-cols-2 gap-6">
                            <div class="grid gap-2">
                                <Label for="name">Nama</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    required
                                    autofocus
                                    :tabindex="1"
                                    autocomplete="name"
                                    placeholder="Nama lengkap"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="email">Email</Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    disabled
                                    type="email"
                                    required
                                    :tabindex="2"
                                    autocomplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError :message="form.errors.email" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password">Password</Label>
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    :tabindex="3"
                                    autocomplete="new-password"
                                    placeholder="Password"
                                />
                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="role">Role</Label>
                                <Select
                                    id="role"
                                    v-model="form.role"
                                    name="role"
                                    :tabindex="4"
                                    placeholder="Select a role"
                                >
                                    <SelectTrigger class="w-full">
                                        <SelectValue :placeholder="'Select a role'" />
                                    </SelectTrigger>
                                    <SelectContent side="top">
                                        <SelectItem
                                            v-for="role in roles"
                                            :key="role.id"
                                            :value="role.name"
                                        >
                                            {{ role.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.role" />
                            </div>

                            <div class="grid col-span-2 gap-2">
                                <Label>Hak Akses Langsung</Label>
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
                                            <template v-if="table.getRowModel().rows.length > 0">
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
                                                                :render="cell.column.columnDef.cell"
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
                                                        Tidak ada hak akses.
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
                                class="col-span-2 mt-2 w-full cursor-pointer"
                                tabindex="5"
                                :disabled="form.processing"
                            >
                                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                                Edit user
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
