<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, LoaderCircle } from 'lucide-vue-next'
import { Permission } from '@/types'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import Card from '@/Components/ui/card/Card.vue'
import CardHeader from '@/Components/ui/card/CardHeader.vue'
import CardTitle from '@/Components/ui/card/CardTitle.vue'
import CardDescription from '@/Components/ui/card/CardDescription.vue'
import CardContent from '@/Components/ui/card/CardContent.vue'
import Label from '@/Components/ui/label/Label.vue'
import Input from '@/Components/ui/input/Input.vue'
import InputError from '@/Components/InputError.vue'
import { CreateRoleRequest } from '@/types/formRequests'
import { ColumnDef, FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table'
import { h } from 'vue'
import Checkbox from '@/Components/ui/checkbox/Checkbox.vue'
import Table from '@/Components/ui/table/Table.vue'
import TableHeader from '@/Components/ui/table/TableHeader.vue'
import TableRow from '@/Components/ui/table/TableRow.vue'
import TableHead from '@/Components/ui/table/TableHead.vue'
import TableBody from '@/Components/ui/table/TableBody.vue'
import TableCell from '@/Components/ui/table/TableCell.vue'
import RoleForm from './components/RoleForm.vue'

const props = defineProps<{
    permissions: Permission[]
}>()
</script>

<template>
    <Head title="Buat Role" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Button
                    variant="ghost"
                    class="p-0 mr-2"
                    size="sm"
                    @click="$inertia.visit(route('admin.roles.index'))"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Kembali
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Buat Role Baru</h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Buat Role Baru</CardTitle>
                    <CardDescription> Isi detail untuk membuat role baru. </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <RoleForm
                        :permissions="props.permissions"
                        :submit-url="route('admin.roles.store')"
                    />
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
