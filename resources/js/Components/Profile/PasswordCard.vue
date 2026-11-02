<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

import FormField from '@/Components/app/FormField.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

// W66 — ganti kata sandi sendiri lewat route Laravel `password.update`.
const form = useForm({ current_password: '', password: '', password_confirmation: '' })

const fields = [
    { key: 'current_password', label: 'Password Lama', autocomplete: 'current-password' },
    { key: 'password', label: 'Password Baru', autocomplete: 'new-password' },
    {
        key: 'password_confirmation',
        label: 'Konfirmasi Password Baru',
        autocomplete: 'new-password',
    },
] as const

const save = () =>
    form.put(route('password.update'), { preserveScroll: true, onSuccess: () => form.reset() })
</script>

<template>
    <SectionCard title="Ganti Password">
        <form aria-label="Ganti Password" class="flex flex-col gap-4" @submit.prevent="save">
            <div class="grid gap-4 md:grid-cols-3">
                <FormField
                    v-for="field in fields"
                    :id="`password-${field.key}`"
                    :key="field.key"
                    :label="field.label"
                    required
                    :error="form.errors[field.key]"
                >
                    <template #default="{ describedBy }">
                        <Input
                            :id="`password-${field.key}`"
                            v-model="form[field.key]"
                            type="password"
                            placeholder="••••••••"
                            :autocomplete="field.autocomplete"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>
            </div>
            <Button type="submit" variant="outline" class="self-start" :disabled="form.processing">
                Ubah Password
            </Button>
        </form>
    </SectionCard>
</template>
