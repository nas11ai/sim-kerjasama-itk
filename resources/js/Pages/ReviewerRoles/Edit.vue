<!-- resources/js/Pages/Admin/ReviewerRoles/Edit.vue -->
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Checkbox } from '@/Components/ui/checkbox'
import { Badge } from '@/Components/ui/badge'
import { ArrowLeft, Shield, Info } from 'lucide-vue-next'

interface ReviewerRole {
    id: number
    name: string
    is_active: boolean
    created_at: string
}

interface Props {
    reviewerRole: ReviewerRole
}

const props = defineProps<Props>()

const form = useForm({
    name: props.reviewerRole.name,
    is_active: props.reviewerRole.is_active,
})

const submit = () => {
    form.put(route('admin.reviewer-roles.update', props.reviewerRole.id))
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}
</script>

<template>
    <Head title="Edit Reviewer Role" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="sm"
                    @click="$inertia.visit(route('admin.reviewer-roles.index'))"
                >
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Kembali
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Edit Role Reviewer
                </h2>
            </div>
        </template>

        <div class="max-w-2xl mx-auto space-y-6">
            <!-- Current Role Info -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Info class="h-5 w-5" />
                        Informasi Role Saat Ini
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-muted/50 rounded-lg">
                            <div>
                                <h3 class="font-semibold text-lg flex items-center gap-2">
                                    <Shield class="h-5 w-5" />
                                    {{ reviewerRole.name }}
                                </h3>
                                <div class="flex items-center gap-2 mt-2">
                                    <Badge
                                        :variant="
                                            reviewerRole.is_active ? 'default' : 'destructive'
                                        "
                                    >
                                        {{ reviewerRole.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </Badge>
                                    <Badge variant="outline"> ID: {{ reviewerRole.id }} </Badge>
                                </div>
                            </div>
                        </div>

                        <div>
                            <Label class="text-sm font-medium text-muted-foreground">Created</Label>
                            <p class="font-medium">
                                {{ formatDate(reviewerRole.created_at) }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Edit Form -->
            <Card>
                <CardHeader>
                    <CardTitle>Perbarui Detail Role</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-6" @submit.prevent="submit">
                        <!-- Role Name -->
                        <div class="space-y-2">
                            <Label for="name">Nama Role *</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                placeholder="Masukkan nama role reviewer"
                                required
                            />
                            <p class="text-xs text-muted-foreground">
                                Pilih nama yang deskriptif yang dengan jelas mengidentifikasi role
                                dan tanggung jawab reviewer.
                            </p>
                            <p v-if="form.errors.name" class="text-sm text-destructive">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Active Status -->
                        <div class="space-y-2">
                            <div class="flex items-center space-x-2">
                                <Checkbox id="is_active" v-model="form.is_active" />
                                <Label
                                    for="is_active"
                                    class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                >
                                    Role Aktif
                                </Label>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Hanya role aktif yang dapat ditugaskan kepada reviewer.
                                Menonaktifkan role akan menyembunyikannya dari penugasan baru tetapi
                                mempertahankan yang sudah ada.
                            </p>
                            <p v-if="form.errors.is_active" class="text-sm text-destructive">
                                {{ form.errors.is_active }}
                            </p>
                        </div>

                        <!-- Warning for Status Change -->
                        <div
                            v-if="reviewerRole.is_active && !form.is_active"
                            class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg"
                        >
                            <div class="flex">
                                <div class="shrink-0">
                                    <svg
                                        class="h-5 w-5 text-yellow-400"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">
                                        Menonaktifkan Role
                                    </h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>
                                            Menonaktifkan role ini akan mencegahnya untuk ditugaskan
                                            kepada reviewer baru. Reviewer yang sudah memiliki role
                                            ini akan tetap mempertahankan penugasan mereka, tetapi
                                            role ini tidak akan tersedia untuk pilihan baru.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Success for Activation -->
                        <div
                            v-if="!reviewerRole.is_active && form.is_active"
                            class="p-4 bg-green-50 border border-green-200 rounded-lg"
                        >
                            <div class="flex">
                                <div class="shrink-0">
                                    <svg
                                        class="h-5 w-5 text-green-400"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L8.093 10.5a.75.75 0 00-1.186.918l1.677 2.166a.75.75 0 001.199-.043l3.733-5.242z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-green-800">
                                        Mengaktifkan Role
                                    </h3>
                                    <div class="mt-2 text-sm text-green-700">
                                        <p>
                                            Mengaktifkan role ini akan membuatnya tersedia untuk
                                            penugasan kepada reviewer baru.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end space-x-3">
                            <Button
                                type="button"
                                variant="outline"
                                @click="$inertia.visit(route('admin.reviewer-roles.index'))"
                            >
                                Batal
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Memperbarui...' : 'Perbarui Role' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
