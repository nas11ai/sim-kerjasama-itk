<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ArrowLeft } from 'lucide-vue-next'
import { Permission } from '@/types'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import Card from '@/Components/ui/card/Card.vue'
import CardHeader from '@/Components/ui/card/CardHeader.vue'
import CardTitle from '@/Components/ui/card/CardTitle.vue'
import CardDescription from '@/Components/ui/card/CardDescription.vue'
import CardContent from '@/Components/ui/card/CardContent.vue'
import { computed, h } from 'vue'
import RoleForm from './components/RoleForm.vue'

const props = defineProps<{
    role: { id: number; name: string; permissions: string[] }
    permissions: Permission[]
}>()

const roleId = computed(() => props.role?.id ?? null)
</script>

<template>
    <Head title="Edit Role" />

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
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Edit Role</h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Edit Role</CardTitle>
                    <CardDescription> Isi detail untuk mengedit role. </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <RoleForm
                        v-if="roleId"
                        :permissions="props.permissions"
                        :default-role="props.role"
                        :submit-url="route('admin.roles.update', props.role.id)"
                        is-edit
                    />
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
