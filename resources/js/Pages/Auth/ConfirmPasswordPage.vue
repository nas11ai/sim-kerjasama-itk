<script setup lang="ts">
// Frame: — (tidak ada di wireframe; route password.confirm tetap hidup, lihat W3)
import { Head, useForm } from '@inertiajs/vue3'

import AuthHeading from '@/Components/Auth/AuthHeading.vue'
import FormField from '@/Components/app/FormField.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import AuthLayout from '@/Layouts/AuthLayout.vue'

const form = useForm({ password: '' })

const submit = () => form.post(route('password.confirm'), { onFinish: () => form.reset() })
</script>

<template>
    <Head title="Konfirmasi Password" />
    <AuthLayout>
        <AuthHeading
            title="Konfirmasi Password"
            subtitle="Area ini butuh konfirmasi password sebelum dilanjutkan."
        />

        <form class="space-y-4" @submit.prevent="submit">
            <FormField id="password" label="Password" :error="form.errors.password" required>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    autofocus
                    required
                />
            </FormField>

            <Button type="submit" class="w-full" :disabled="form.processing">Konfirmasi</Button>
        </form>
    </AuthLayout>
</template>
