<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '../../Components/ui/button'
import { ArrowLeft, LoaderCircle } from 'lucide-vue-next'
import { Role } from '@/types'
import { Label } from '@/Components/ui/label'
import { Input } from '@/Components/ui/input'
import InputError from '@/Components/InputError.vue'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { CreateUserRequest } from '@/types/formRequests'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'

const form = useForm<CreateUserRequest>({
    name: '',
    email: '',
    password: '',
    role: '',
})

defineProps<{
    roles: Role[]
}>()

const submit = () => {
    form.post(route('admin.users.store'))
}
</script>

<template>
    <Head title="Buat User" />

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
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Buat User Baru</h2>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>Buat User Baru</CardTitle>
                        <CardDescription>Isi detail untuk membuat user baru.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div class="flex flex-col gap-2">
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

                            <div class="flex flex-col gap-2">
                                <Label for="email">Email</Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    :tabindex="2"
                                    autocomplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError :message="form.errors.email" />
                            </div>

                            <div class="flex flex-col gap-2">
                                <Label for="password">Password</Label>
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    required
                                    :tabindex="3"
                                    autocomplete="new-password"
                                    placeholder="Password"
                                />
                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="flex flex-col gap-2">
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

                            <Button
                                type="submit"
                                class="col-span-2 mt-2 w-full cursor-pointer"
                                tabindex="5"
                                :disabled="form.processing"
                            >
                                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                                Buat user
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
