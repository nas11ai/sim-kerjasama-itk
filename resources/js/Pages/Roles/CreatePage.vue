<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ArrowLeft } from 'lucide-vue-next'
import { Permission } from '@/types'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import Card from '@/Components/ui/card/UiCard.vue'
import CardHeader from '@/Components/ui/card/CardHeader.vue'
import CardTitle from '@/Components/ui/card/CardTitle.vue'
import CardDescription from '@/Components/ui/card/CardDescription.vue'
import CardContent from '@/Components/ui/card/CardContent.vue'
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
