<!-- resources/js/Pages/Admin/Reviewers/Create.vue -->
<script setup lang="ts">
import { route } from 'ziggy-js'
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { ArrowLeft, UserPlus } from 'lucide-vue-next'

interface User {
    id: number
    name: string
    email: string
}

interface ReviewerRole {
    id: number
    name: string
}

interface Props {
    users: User[]
    reviewerRoles: ReviewerRole[]
}

const props = defineProps<Props>()

const form = useForm({
    user_id: '',
    reviewer_role_id: '',
    start_date: new Date().toISOString().split('T')[0], // Today's date
    end_date: '',
})

const submit = () => {
    form.post(route('admin.reviewers.store'))
}
</script>

<template>
    <Head title="Tambah Reviewer" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="sm"
                    @click="$inertia.visit(route('admin.reviewers.index'))"
                >
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Kembali
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Tambah Reviewer</h2>
            </div>
        </template>

        <div class="max-w-2xl mx-auto">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <UserPlus class="h-5 w-5" />
                        Informasi Reviewer
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-6" @submit.prevent="submit">
                        <!-- User Selection -->
                        <div class="space-y-2">
                            <Label for="user_id">User *</Label>
                            <Select v-model="form.user_id" required>
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder="Pilih user untuk ditugaskan sebagai reviewer"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="user in props.users"
                                        :key="user.id"
                                        :value="user.id.toString()"
                                    >
                                        <div class="flex flex-col">
                                            {{ user.name }}
                                            <span class="text-xs italic text-muted-foreground"
                                                >({{ user.email }})</span
                                            >
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.user_id" class="text-sm text-destructive">
                                {{ form.errors.user_id }}
                            </p>
                            <p
                                v-if="props.users.length === 0"
                                class="text-sm text-muted-foreground"
                            >
                                Tidak ada user yang tersedia. Semua user yang memenuhi syarat
                                mungkin sudah menjadi reviewer aktif.
                            </p>
                        </div>

                        <!-- Reviewer Role -->
                        <div class="space-y-2">
                            <Label for="reviewer_role_id">Role Reviewer *</Label>
                            <Select v-model="form.reviewer_role_id" required>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih role reviewer" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="role in props.reviewerRoles"
                                        :key="role.id"
                                        :value="role.id.toString()"
                                    >
                                        {{ role.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.reviewer_role_id" class="text-sm text-destructive">
                                {{ form.errors.reviewer_role_id }}
                            </p>
                        </div>

                        <!-- Start Date -->
                        <div class="space-y-2">
                            <Label for="start_date">Tanggal Mulai *</Label>
                            <Input id="start_date" v-model="form.start_date" type="date" required />
                            <p v-if="form.errors.start_date" class="text-sm text-destructive">
                                {{ form.errors.start_date }}
                            </p>
                        </div>

                        <!-- End Date -->
                        <div class="space-y-2">
                            <Label for="end_date">Tanggal Selesai (Opsional)</Label>
                            <Input id="end_date" v-model="form.end_date" type="date" />
                            <p class="text-xs text-muted-foreground">
                                Biarkan kosong jika tidak ada tanggal selesai (penugasan reviewer
                                permanen)
                            </p>
                            <p v-if="form.errors.end_date" class="text-sm text-destructive">
                                {{ form.errors.end_date }}
                            </p>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end space-x-3">
                            <Button
                                type="button"
                                variant="outline"
                                @click="$inertia.visit(route('admin.reviewers.index'))"
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                :disabled="form.processing || props.users.length === 0"
                            >
                                {{ form.processing ? 'Membuat...' : 'Tambah Reviewer' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
