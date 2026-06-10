<!-- resources/js/Pages/Admin/ReviewerRoles/Create.vue -->
<script setup lang="ts">
import { route } from 'ziggy-js'
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Checkbox } from '@/Components/ui/checkbox'
import { ArrowLeft, Shield } from 'lucide-vue-next'

const form = useForm({
    name: '',
    is_active: true,
})

const submit = () => {
    form.post(route('admin.reviewer-roles.store'))
}
</script>

<template>
    <Head title="Buat Role Reviewer" />

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
                    Buat Role Reviewer
                </h2>
            </div>
        </template>

        <div class="max-w-2xl mx-auto">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Shield class="h-5 w-5" />
                        Informasi Role
                    </CardTitle>
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
                                placeholder="Masukkan nama role reviewer (misalnya, Reviewer Fakultas, Reviewer Eksternal)"
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
                                Hanya role yang aktif yang dapat diberikan kepada reviewer. Role
                                yang tidak aktif akan disembunyikan dari pilihan, tetapi tetap
                                mempertahankan penugasan yang sudah ada.
                            </p>
                            <p v-if="form.errors.is_active" class="text-sm text-destructive">
                                {{ form.errors.is_active }}
                            </p>
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
                                {{ form.processing ? 'Membuat...' : 'Buat Role' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
