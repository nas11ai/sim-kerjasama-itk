<!-- resources\js\Pages\ReviewerRoles\Show.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { ArrowLeft, Edit, Shield, Calendar, User, CheckCircle, XCircle } from 'lucide-vue-next'

interface User {
    id: number
    name: string
    email: string
}

interface Reviewer {
    id: number
    user_id: number
    reviewer_role_id: number
    start_date: string
    end_date: string | null
    created_at: string
    updated_at: string
    user: User
}

interface ReviewerRole {
    id: number
    name: string
    is_active: boolean
    created_at: string
    updated_at: string
    reviewers_count?: number
    reviewers: Reviewer[]
}

interface Props {
    reviewerRole: ReviewerRole
}

const props = defineProps<Props>()

const formatDateTime = (date: string) => {
    return new Date(date).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const handleEdit = () => {
    router.visit(route('admin.reviewer-roles.edit', props.reviewerRole.id))
}

const goBack = () => {
    router.visit(route('admin.reviewer-roles.index'))
}
</script>

<template>
    <Head :title="`Detail Reviewer Role - ${reviewerRole.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="$inertia.visit(route('admin.reviewer-roles.index'))"
                    >
                        <ArrowLeft class="h-4 w-4 mr-2" />
                        Kembali
                    </Button>
                    <div class="flex items-center gap-3 ml-4">
                        <div class="p-2 bg-primary/10 rounded-lg">
                            <Shield class="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h2
                                class="text-xl font-semibold leading-tight text-gray-800 flex items-center gap-2"
                            >
                                {{ reviewerRole.name }}
                                <Badge
                                    :variant="reviewerRole.is_active ? 'outline' : 'secondary'"
                                    class="text-xs"
                                >
                                    {{ reviewerRole.is_active ? 'Aktif' : 'Nonaktif' }}
                                </Badge>
                            </h2>
                            <p class="text-sm text-muted-foreground mt-0.5">
                                Detail informasi reviewer role
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" class="gap-2" @click="handleEdit">
                        <Edit class="h-4 w-4" />
                        Edit
                    </Button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6 py-6 px-4 sm:px-6 lg:px-8">
            <!-- Role Information Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Shield class="h-5 w-5" />
                        Informasi Role Reviewer
                    </CardTitle>
                    <CardDescription> Detail informasi role reviewer </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-muted-foreground"
                                >Nama Role</label
                            >
                            <p class="text-base font-semibold">
                                {{ reviewerRole.name }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-muted-foreground">Status</label>
                            <div>
                                <Badge
                                    :variant="reviewerRole.is_active ? 'outline' : 'secondary'"
                                    class="gap-1"
                                >
                                    <component
                                        :is="reviewerRole.is_active ? CheckCircle : XCircle"
                                        class="h-3 w-3"
                                    />
                                    {{ reviewerRole.is_active ? 'Aktif' : 'Nonaktif' }}
                                </Badge>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label
                                class="text-sm font-medium text-muted-foreground flex items-center gap-1"
                            >
                                <Calendar class="h-3 w-3" />
                                Dibuat Pada
                            </label>
                            <p class="text-sm">
                                {{ formatDateTime(reviewerRole.created_at) }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <label
                                class="text-sm font-medium text-muted-foreground flex items-center gap-1"
                            >
                                <Calendar class="h-3 w-3" />
                                Terakhir Diperbarui
                            </label>
                            <p class="text-sm">
                                {{ formatDateTime(reviewerRole.updated_at) }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
